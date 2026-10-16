<?php

namespace App\Support;

use App\Models\Appareil;
use App\Models\Boutique;
use App\Models\Categorie;
use App\Models\Client;
use App\Models\Depense;
use App\Models\Fournisseur;
use App\Models\MouvementStock;
use App\Models\Produit;
use App\Models\Remboursement;
use App\Models\User;
use App\Models\Vente;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Échanges avec les applications installées (téléphone, ordinateur).
 *
 * envoyer() : l'appareil envoie ce qu'il a fait hors ligne. Chaque élément porte un uuid
 *             créé par l'appareil : un renvoi (connexion coupée pendant l'envoi) ne crée
 *             pas de doublon. Les éléments refusés sont listés avec la raison.
 * recevoir(): l'appareil récupère tout ce qui a changé depuis sa dernière révision.
 */
class Synchronisation
{
    // Nombre maximum de lignes par table dans une réponse ; l'appareil redemande si "encore".
    public static int $limite = 500;

    private array $acceptes = [];

    private array $rejets = [];

    public function __construct(private User $user, private Appareil $appareil) {}

    public function envoyer(array $changements): array
    {
        $gerant = $this->user->estGerant();

        // Ordre important : les ventes font référence aux clients et aux produits
        if ($gerant) {
            $this->chaque($changements, 'categories', fn ($d) => $this->enregistrerSimple(Categorie::class, $d, [
                'nom' => ['required', 'string', 'max:80'],
            ]));
            $this->chaque($changements, 'fournisseurs', fn ($d) => $this->enregistrerSimple(Fournisseur::class, $d, [
                'nom' => 'required|string|max:150', 'telephone' => 'nullable|string|max:30',
                'adresse' => 'nullable|string|max:255', 'note' => 'nullable|string|max:1000',
            ]));
        }
        $this->chaque($changements, 'clients', fn ($d) => $this->enregistrerSimple(Client::class, $d, [
            'nom' => 'required|string|max:150', 'telephone' => 'nullable|string|max:30', 'adresse' => 'nullable|string|max:255',
        ]));
        if ($gerant) {
            $this->chaque($changements, 'produits', fn ($d) => $this->enregistrerProduit($d));
            $this->chaque($changements, 'mouvements', fn ($d) => $this->enregistrerMouvement($d));
        }
        $this->chaque($changements, 'ventes', fn ($d) => Vente::importer($this->valider($d, [
            'numero' => 'nullable|string|max:40',
            'client_uuid' => 'nullable|uuid',
            'montant_paye' => 'required|integer|min:0',
            'mode_paiement' => 'nullable|string',
            'created_at' => 'nullable|date',
            'lignes' => 'required|array|min:1',
            'lignes.*.uuid' => 'nullable|uuid',
            'lignes.*.produit_uuid' => 'required|uuid',
            'lignes.*.designation' => 'nullable|string|max:150',
            'lignes.*.quantite' => 'required|integer|min:1',
            'lignes.*.prix_unitaire' => 'required|integer|min:0',
        ]), $this->appareil, $this->user));
        if ($gerant) {
            $this->chaque($changements, 'annulations', fn ($d) => $this->enregistrerAnnulation($d));
        }
        $this->chaque($changements, 'remboursements', fn ($d) => $this->enregistrerRemboursement($d));
        if ($gerant) {
            $this->chaque($changements, 'depenses', fn ($d) => $this->enregistrerDepense($d));
        }

        return ['acceptes' => $this->acceptes, 'rejets' => $this->rejets];
    }

    public function recevoir(int $depuis): array
    {
        $gerant = $this->user->estGerant();

        $sources = [
            'boutique' => Boutique::query(),
            'utilisateurs' => User::query(),
            'categories' => Categorie::query(),
            'fournisseurs' => Fournisseur::query(),
            'produits' => Produit::with('categorie:id,uuid'),
            'clients' => Client::query(),
            'ventes' => Vente::with('lignes.produit:id,uuid', 'client:id,uuid', 'user:id,uuid'),
            'remboursements' => Remboursement::with('client:id,uuid', 'user:id,uuid'),
        ];
        if ($gerant) {
            $sources['depenses'] = Depense::with('fournisseur:id,uuid', 'user:id,uuid');
        }

        // On prend au plus $limite lignes par table ; si une table est tronquée, on s'arrête
        // à sa dernière révision pour toutes les tables, afin de ne rien sauter.
        $resultats = [];
        $plafond = (int) DB::table('sync_compteur')->value('valeur');
        $encore = false;
        foreach ($sources as $cle => $requete) {
            $lignes = $requete->where('revision', '>', $depuis)->orderBy('revision')->limit(self::$limite + 1)->get();
            if ($lignes->count() > self::$limite) {
                $encore = true;
                $plafond = min($plafond, $lignes[self::$limite - 1]->revision);
            }
            $resultats[$cle] = $lignes;
        }

        $donnees = [];
        foreach ($resultats as $cle => $lignes) {
            $donnees[$cle] = $lignes->filter(fn ($l) => $l->revision <= $plafond)
                ->map(fn ($l) => $this->exporter($cle, $l))->values();
        }

        $suppressions = DB::table('suppressions')->where('revision', '>', $depuis)->where('revision', '<=', $plafond)
            ->get(['table_nom', 'uuid']);

        $this->appareil->update(['derniere_synchro' => now()]);

        return [
            'revision' => $plafond,
            'encore' => $encore,
            'donnees' => $donnees,
            'suppressions' => $suppressions,
        ];
    }

    private function exporter(string $table, $l): array
    {
        $date = fn ($d) => $d?->toIso8601String();

        return match ($table) {
            'boutique' => ['uuid' => $l->uuid, 'nom' => $l->nom, 'adresse' => $l->adresse, 'telephone' => $l->telephone, 'pied_ticket' => $l->pied_ticket],
            'utilisateurs' => ['uuid' => $l->uuid, 'nom' => $l->name, 'role' => $l->role, 'actif' => $l->actif],
            'categories' => ['uuid' => $l->uuid, 'nom' => $l->nom],
            'fournisseurs' => ['uuid' => $l->uuid, 'nom' => $l->nom, 'telephone' => $l->telephone, 'adresse' => $l->adresse, 'note' => $l->note],
            'clients' => ['uuid' => $l->uuid, 'nom' => $l->nom, 'telephone' => $l->telephone, 'adresse' => $l->adresse],
            'produits' => [
                'uuid' => $l->uuid, 'nom' => $l->nom, 'code' => $l->code, 'categorie_uuid' => $l->categorie?->uuid,
                'unite' => $l->unite, 'prix_achat' => $l->prix_achat, 'prix_vente' => $l->prix_vente,
                'stock' => $l->stock, 'seuil_alerte' => $l->seuil_alerte, 'actif' => $l->actif,
            ],
            'ventes' => [
                'uuid' => $l->uuid, 'numero' => $l->numero, 'client_uuid' => $l->client?->uuid, 'user_uuid' => $l->user?->uuid,
                'total' => $l->total, 'montant_paye' => $l->montant_paye, 'mode_paiement' => $l->mode_paiement,
                'statut' => $l->statut, 'motif_annulation' => $l->motif_annulation, 'created_at' => $date($l->created_at),
                'lignes' => $l->lignes->map(fn ($x) => [
                    'uuid' => $x->uuid, 'produit_uuid' => $x->produit?->uuid, 'designation' => $x->designation,
                    'quantite' => $x->quantite, 'prix_unitaire' => $x->prix_unitaire,
                    'prix_achat_unitaire' => $x->prix_achat_unitaire, 'total' => $x->total,
                ])->values(),
            ],
            'remboursements' => [
                'uuid' => $l->uuid, 'client_uuid' => $l->client?->uuid, 'user_uuid' => $l->user?->uuid, 'montant' => $l->montant,
                'mode_paiement' => $l->mode_paiement, 'note' => $l->note, 'created_at' => $date($l->created_at),
            ],
            'depenses' => [
                'uuid' => $l->uuid, 'libelle' => $l->libelle, 'categorie' => $l->categorie, 'montant' => $l->montant,
                'date_depense' => $l->date_depense?->toDateString(), 'fournisseur_uuid' => $l->fournisseur?->uuid,
                'user_uuid' => $l->user?->uuid,
            ],
        };
    }

    private function chaque(array $changements, string $table, callable $traiter): void
    {
        foreach ($changements[$table] ?? [] as $element) {
            $uuid = is_array($element) ? ($element['uuid'] ?? $element['vente_uuid'] ?? null) : null;
            try {
                if (! is_array($element) || ! is_string($uuid) || ! preg_match('/^[0-9a-f-]{36}$/i', $uuid)) {
                    throw ValidationException::withMessages(['uuid' => 'Identifiant manquant.']);
                }
                $traiter($element);
                $this->acceptes[$table][] = $uuid;
            } catch (ValidationException $e) {
                $this->rejets[] = ['table' => $table, 'uuid' => $uuid, 'raison' => collect($e->errors())->flatten()->implode(' ')];
            } catch (Throwable $e) {
                report($e);
                $this->rejets[] = ['table' => $table, 'uuid' => $uuid, 'raison' => 'Erreur du serveur.'];
            }
        }
    }

    private function valider(array $donnees, array $regles): array
    {
        return validator($donnees, $regles + ['uuid' => 'required|uuid'])->validate();
    }

    // Fiches simples (catégorie, fournisseur, client) : la dernière modification reçue gagne
    private function enregistrerSimple(string $classe, array $donnees, array $regles): void
    {
        $valide = $this->valider($donnees, $regles);
        $modele = $classe::parUuid($valide['uuid']) ?? new $classe(['uuid' => $valide['uuid']]);
        $modele->fill($valide)->save();
    }

    private function enregistrerProduit(array $donnees): void
    {
        $existant = Produit::parUuid($donnees['uuid'] ?? null);
        $valide = $this->valider($donnees, [
            'nom' => 'required|string|max:150',
            'code' => ['nullable', 'string', 'max:60', Rule::unique('produits', 'code')->ignore($existant?->id)],
            'categorie_uuid' => 'nullable|uuid',
            'unite' => 'required|string|max:30',
            'prix_achat' => 'required|integer|min:0',
            'prix_vente' => 'required|integer|min:0',
            'seuil_alerte' => 'required|integer|min:0',
            'actif' => 'required|boolean',
        ]);

        // Le stock n'est jamais écrit ici : il ne change que par des mouvements
        $produit = $existant ?? new Produit(['uuid' => $valide['uuid']]);
        $produit->fill($valide);
        $produit->categorie_id = Categorie::parUuid($valide['categorie_uuid'] ?? null)?->id;
        $produit->save();
    }

    // Entrée de marchandise ou ajustement d'inventaire fait sur l'appareil
    private function enregistrerMouvement(array $donnees): void
    {
        $valide = $this->valider($donnees, [
            'produit_uuid' => 'required|uuid|exists:produits,uuid',
            'type' => 'required|in:entree,ajustement',
            'quantite' => 'required|integer|not_in:0',
            'prix_achat_unitaire' => 'nullable|integer|min:0',
            'fournisseur_uuid' => 'nullable|uuid',
            'note' => 'nullable|string|max:255',
            'maj_prix' => 'nullable|boolean',
            'created_at' => 'nullable|date',
        ]);
        if (MouvementStock::where('uuid', $valide['uuid'])->exists()) {
            return;
        }

        DB::transaction(function () use ($valide) {
            $produit = Produit::where('uuid', $valide['produit_uuid'])->lockForUpdate()->firstOrFail();
            if ($valide['type'] === 'entree' && isset($valide['prix_achat_unitaire']) && ($valide['maj_prix'] ?? true)) {
                $produit->prix_achat = $valide['prix_achat_unitaire'];
            }
            $produit->mouvementer((int) $valide['quantite'], $valide['type'], $this->user->id, [
                'uuid' => $valide['uuid'],
                'prix_achat_unitaire' => $valide['prix_achat_unitaire'] ?? null,
                'fournisseur_id' => Fournisseur::parUuid($valide['fournisseur_uuid'] ?? null)?->id,
                'note' => $valide['note'] ?? null,
            ]);
        });
    }

    private function enregistrerAnnulation(array $donnees): void
    {
        $valide = validator($donnees, [
            'vente_uuid' => 'required|uuid|exists:ventes,uuid',
            'motif' => 'required|string|max:255',
        ])->validate();

        Vente::parUuid($valide['vente_uuid'])->annuler($this->user->id, $valide['motif']);
    }

    private function enregistrerRemboursement(array $donnees): void
    {
        $valide = $this->valider($donnees, [
            'client_uuid' => 'required|uuid|exists:clients,uuid',
            'montant' => 'required|integer|min:1',
            'mode_paiement' => ['required', Rule::in(array_keys(Vente::MODES))],
            'note' => 'nullable|string|max:255',
            'created_at' => 'nullable|date',
        ]);
        if (Remboursement::parUuid($valide['uuid'])) {
            return;
        }

        $remboursement = new Remboursement([
            'uuid' => $valide['uuid'],
            'client_id' => Client::parUuid($valide['client_uuid'])->id,
            'user_id' => $this->user->id,
            'montant' => $valide['montant'],
            'mode_paiement' => $valide['mode_paiement'],
            'note' => $valide['note'] ?? null,
        ]);
        $remboursement->created_at = $this->dateAppareil($valide['created_at'] ?? null);
        $remboursement->save();
    }

    private function enregistrerDepense(array $donnees): void
    {
        $valide = $this->valider($donnees, [
            'libelle' => 'required|string|max:200',
            'categorie' => ['required', Rule::in(Depense::CATEGORIES)],
            'montant' => 'required|integer|min:1',
            'date_depense' => 'required|date',
            'fournisseur_uuid' => 'nullable|uuid',
        ]);
        $depense = Depense::parUuid($valide['uuid']) ?? new Depense(['uuid' => $valide['uuid'], 'user_id' => $this->user->id]);
        $depense->fill([
            'libelle' => $valide['libelle'],
            'categorie' => $valide['categorie'],
            'montant' => $valide['montant'],
            'date_depense' => $valide['date_depense'],
            'fournisseur_id' => Fournisseur::parUuid($valide['fournisseur_uuid'] ?? null)?->id,
        ])->save();
    }

    private function dateAppareil(?string $date): Carbon
    {
        $date = $date ? Carbon::parse($date)->setTimezone(config('app.timezone')) : now();

        return $date->isFuture() ? now() : $date;
    }
}
