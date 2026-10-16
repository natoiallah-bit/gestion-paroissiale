<?php

namespace App\Support;

use App\Models\Appareil;
use App\Models\User;
use App\Models\Vicariat;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Échanges avec les applications installées (téléphone, ordinateur).
 *
 * envoyer() : l'appareil envoie ce qu'il a fait hors ligne. Chaque élément porte un uuid
 *             créé par l'appareil : un renvoi (connexion coupée pendant l'envoi) ne crée
 *             pas de doublon. Les éléments refusés sont listés avec la raison.
 * recevoir(): l'appareil récupère tout ce qui a changé depuis sa dernière révision.
 *
 * Les fiches (fidèles, CEB, sacrements…) sont décrites dans Ressources : la dernière
 * modification reçue gagne, comme sur le site.
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
        // L'ordre des ressources respecte les dépendances (paroisse → CEB → fidèle → sacrement)
        foreach (Ressources::cles() as $cle) {
            if (! Ressources::peutEcrire($this->user, $cle)) {
                foreach ($changements[$cle] ?? [] as $element) {
                    $this->rejeter($cle, $element, 'Votre compte n\'a pas le droit de modifier ces données.');
                }

                continue;
            }
            $this->chaque($changements, $cle, fn ($d) => $this->enregistrer($cle, $d));
        }

        return ['acceptes' => $this->acceptes, 'rejets' => $this->rejets];
    }

    public function recevoir(int $depuis): array
    {
        $sources = ['vicariat' => Vicariat::query()];
        $sources['utilisateurs'] = $this->user->estAdmin() ? User::with('paroisse:id,uuid') : User::with('paroisse:id,uuid')->where('id', $this->user->id);
        foreach (Ressources::cles() as $cle) {
            if (! Ressources::peutLire($this->user, $cle)) {
                continue;
            }
            $def = Ressources::get($cle);
            $requete = $def['modele']::query()->with($this->relations($cle));
            $sources[$cle] = Portee::restreindre($requete, $this->user, $cle);
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

    private function relations(string $cle): array
    {
        $relations = [];
        foreach (Ressources::get($cle)['champs'] as $champ) {
            if ($champ['type'] === 'ref') {
                $relations[] = Ressources::relation($champ).':id,uuid';
            }
        }
        if ($cle === 'sacrements') {
            $relations[] = 'paroisse:id,uuid';
        }

        return $relations;
    }

    private function exporter(string $cle, $l): array
    {
        return match ($cle) {
            'vicariat' => ['uuid' => $l->uuid, 'nom' => $l->nom, 'diocese' => $l->diocese, 'adresse' => $l->adresse, 'telephone' => $l->telephone, 'pied_extrait' => $l->pied_extrait],
            'utilisateurs' => ['uuid' => $l->uuid, 'nom' => $l->name, 'role' => $l->role, 'actif' => $l->actif, 'paroisse_uuid' => $l->paroisse?->uuid],
            default => Ressources::exporter($cle, $l),
        };
    }

    private function chaque(array $changements, string $cle, callable $traiter): void
    {
        foreach ($changements[$cle] ?? [] as $element) {
            $uuid = is_array($element) ? ($element['uuid'] ?? null) : null;
            try {
                if (! is_array($element) || ! is_string($uuid) || ! preg_match('/^[0-9a-f-]{36}$/i', $uuid)) {
                    throw ValidationException::withMessages(['uuid' => 'Identifiant manquant.']);
                }
                $traiter($element);
                $this->acceptes[$cle][] = $uuid;
            } catch (ValidationException $e) {
                $this->rejets[] = ['table' => $cle, 'uuid' => $uuid, 'raison' => collect($e->errors())->flatten()->implode(' ')];
            } catch (Throwable $e) {
                report($e);
                $this->rejets[] = ['table' => $cle, 'uuid' => $uuid, 'raison' => 'Erreur du serveur.'];
            }
        }
    }

    private function rejeter(string $cle, mixed $element, string $raison): void
    {
        $this->rejets[] = ['table' => $cle, 'uuid' => is_array($element) ? ($element['uuid'] ?? null) : null, 'raison' => $raison];
    }

    private function enregistrer(string $cle, array $donnees): void
    {
        $donnees = Enregistreur::completer($cle, $donnees, $this->user);
        $valide = validator($donnees, Ressources::regles($cle) + ['uuid' => 'required|uuid'])->validate();
        $existant = Ressources::get($cle)['modele']::parUuid($valide['uuid']);

        Enregistreur::enregistrer($cle, $valide, $this->user, $existant);
    }
}
