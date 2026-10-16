<?php

namespace App\Support;

use App\Models\Ceb;
use App\Models\Depot;
use App\Models\Fidele;
use App\Models\Mouvement;
use App\Models\Operation;
use App\Models\Sacrement;
use App\Models\Secteur;
use App\Models\User;
use Carbon\CarbonInterface;

/** Chiffres du tableau de bord et des rapports, limités à ce que le compte a le droit de voir. */
class Statistiques
{
    public static function tableauDeBord(User $user, ?int $secteurId = null): array
    {
        $vue = fn (string $cle, ?int $p = null) => self::portee($cle, $user, $p ?? $secteurId);

        $fideles = $vue('fideles')->where('statut', 'actif');
        $resume = [
            'fideles' => (clone $fideles)->count(),
            'hommes' => (clone $fideles)->where('sexe', 'M')->count(),
            'femmes' => (clone $fideles)->where('sexe', 'F')->count(),
            'familles' => (clone $fideles)->whereNotNull('famille')->where('famille', '!=', '')->distinct()->count('famille'),
            'cebs' => $vue('cebs')->count(),
            'mouvements' => $vue('mouvements')->count(),
            'membres_mouvements' => (int) $vue('mouvements')->sum('nombre_membres'),
            'sacrements_annee' => $vue('sacrements')->whereYear('date_sacrement', today()->year)->count(),
        ];

        $parType = $vue('sacrements')->whereYear('date_sacrement', today()->year)
            ->selectRaw('type, count(*) as n')->groupBy('type')->pluck('n', 'type');

        $activites = $vue('activites')->whereDate('date_activite', '>=', today())->orderBy('date_activite')->orderBy('heure')->limit(8)->get();
        $annonces = $vue('annonces')->whereDate('date_debut', '<=', today())
            ->where(fn ($w) => $w->whereNull('date_fin')->orWhereDate('date_fin', '>=', today()))
            ->orderByDesc('date_debut')->limit(5)->get();

        $finances = Ressources::peutLire($user, 'operations')
            ? ['mois' => self::bilan($user, today()->startOfMonth(), today(), $secteurId),
                'annee' => self::bilan($user, today()->startOfYear(), today(), $secteurId)]
            : null;

        return compact('resume', 'parType', 'activites', 'annonces', 'finances') + [
            'secteurs' => ! $user->estLimiteAUnSecteur() && ! $secteurId && Ressources::peutLire($user, 'secteurs') && $user->estAdmin() ? self::parSecteur($user) : null,
            'comptes' => Ressources::peutLire($user, 'operations') ? self::comptes($user, $secteurId) : null,
        ];
    }

    /** Recettes, dépenses et solde d'une période (dates incluses), détaillés par catégorie. */
    public static function bilan(User $user, CarbonInterface $debut, CarbonInterface $fin, ?int $secteurId = null): array
    {
        $ops = self::portee('operations', $user, $secteurId)
            ->whereBetween('date_operation', [$debut->toDateString(), $fin->toDateString()]);

        $lignes = (clone $ops)->selectRaw('sens, categorie, sum(montant) as total, count(*) as n')
            ->groupBy('sens', 'categorie')->orderByDesc('total')->get();

        $recettes = (int) $lignes->where('sens', 'recette')->sum('total');
        $depenses = (int) $lignes->where('sens', 'depense')->sum('total');

        return [
            'recettes' => $recettes,
            'depenses' => $depenses,
            'solde' => $recettes - $depenses,
            'par_categorie' => $lignes->map(fn ($l) => ['sens' => $l->sens, 'categorie' => $l->categorie, 'total' => (int) $l->total, 'n' => (int) $l->n])->values()->all(),
        ];
    }

    /**
     * Compilation des comptes, du bas vers le haut :
     *  - compte de chaque CEB au conseil économique du secteur (dépôts − retraits) ;
     *  - le secteur compile ses CEB + ses opérations propres, et retire ce qu'il a déjà déposé au vicariat ;
     *  - le conseil économique du vicariat compile les secteurs et sa caisse centrale (dépôts reçus + opérations propres).
     */
    public static function comptes(User $user, ?int $secteurId = null): array
    {
        $secteurs = self::portee('secteurs', $user, null)->orderBy('nom')->when($secteurId, fn ($q) => $q->where('id', $secteurId))->get();
        $ops = self::portee('operations', $user, $secteurId)->selectRaw('secteur_id, ceb_id, sens, sum(montant) as total')->groupBy('secteur_id', 'ceb_id', 'sens')->get();
        $depots = self::portee('depots', $user, $secteurId)->selectRaw('secteur_id, sum(montant) as total, count(*) as n')->groupBy('secteur_id')->get()->keyBy('secteur_id');

        $total = fn ($lignes) => ['recettes' => (int) $lignes->where('sens', 'recette')->sum('total'), 'depenses' => (int) $lignes->where('sens', 'depense')->sum('total')];
        $solde = fn (array $t) => $t + ['solde' => $t['recettes'] - $t['depenses']];

        $lignes = [];
        foreach ($secteurs as $s) {
            $duSecteur = $ops->where('secteur_id', $s->id);
            $cebs = [];
            foreach (Ceb::where('secteur_id', $s->id)->orderBy('nom')->get() as $ceb) {
                $cebs[] = ['ceb' => $ceb] + $solde($total($duSecteur->where('ceb_id', $ceb->id)));
            }
            $propres = $solde($total($duSecteur->whereNull('ceb_id')));
            $compile = $solde($total($duSecteur));
            $depose = (int) ($depots[$s->id]->total ?? 0);
            $lignes[] = [
                'secteur' => $s, 'cebs' => $cebs, 'propres' => $propres, 'compile' => $compile,
                'depose' => $depose, 'nb_depots' => (int) ($depots[$s->id]->n ?? 0), 'reste' => $compile['solde'] - $depose,
            ];
        }

        $central = null;
        if (! $user->estLimiteAUnSecteur() && ! $secteurId) {
            $propres = $solde($total(Operation::whereNull('secteur_id')->selectRaw('sens, sum(montant) as total')->groupBy('sens')->get()));
            $recu = (int) Depot::sum('montant');
            $central = ['recu' => $recu, 'propres' => $propres, 'caisse' => $recu + $propres['solde']];
        }

        return [
            'secteurs' => $lignes, 'central' => $central,
            'total_compile' => array_sum(array_map(fn ($l) => $l['compile']['solde'], $lignes)) + ($central['propres']['solde'] ?? 0),
            'total_depose' => array_sum(array_column($lignes, 'depose')),
            'total_reste' => array_sum(array_column($lignes, 'reste')),
        ];
    }

    /**
     * Cumuls recalculés à chaque dépôt : pour chaque dépôt, le total déjà déposé par son secteur
     * et le total reçu par le vicariat, en comptant les dépôts dans l'ordre (date, puis identifiant : le même ordre que sur les appareils).
     * Calculés à l'affichage plutôt que stockés : jamais faux, même si des appareils saisissent hors ligne.
     */
    public static function cumulsDepots(): array
    {
        $parSecteur = [];
        $general = 0;
        $cumuls = [];
        foreach (Depot::orderBy('date_depot')->orderBy('uuid')->get(['id', 'uuid', 'secteur_id', 'montant']) as $d) {
            $parSecteur[$d->secteur_id] = ($parSecteur[$d->secteur_id] ?? 0) + $d->montant;
            $general += $d->montant;
            $cumuls[$d->id] = ['cumul_secteur' => $parSecteur[$d->secteur_id], 'total_vicariat' => $general];
        }

        return $cumuls;
    }

    /** Vue du vicariat : une ligne par secteur */
    public static function parSecteur(User $user): array
    {
        $lignes = [];
        foreach (Secteur::orderBy('nom')->get() as $p) {
            $fideles = Fidele::where('secteur_id', $p->id)->where('statut', 'actif');
            $lignes[] = [
                'secteur' => $p,
                'fideles' => (clone $fideles)->count(),
                'cebs' => Ceb::where('secteur_id', $p->id)->count(),
                'mouvements' => Mouvement::where('secteur_id', $p->id)->count(),
                'sacrements' => Sacrement::where('secteur_id', $p->id)->whereYear('date_sacrement', today()->year)->count(),
                'recettes' => (int) Operation::where('secteur_id', $p->id)->where('sens', 'recette')->whereYear('date_operation', today()->year)->sum('montant'),
                'depose' => (int) Depot::where('secteur_id', $p->id)->sum('montant'),
            ];
        }

        return $lignes;
    }

    /** Sacrements d'une année par type et par mois (registre statistique) */
    public static function sacrementsParAnnee(User $user, int $annee, ?int $secteurId = null): array
    {
        $lignes = self::portee('sacrements', $user, $secteurId)->whereYear('date_sacrement', $annee)
            ->selectRaw('type, count(*) as n')->groupBy('type')->pluck('n', 'type');

        return collect(Ressources::SACREMENTS)->map(fn ($lib, $type) => ['type' => $type, 'libelle' => $lib, 'n' => (int) ($lignes[$type] ?? 0)])->values()->all();
    }

    private static function portee(string $cle, User $user, ?int $secteurId)
    {
        $requete = Portee::restreindre(Ressources::get($cle)['modele']::query(), $user, $cle);
        if ($secteurId && $cle !== 'secteurs') {
            $requete->where('secteur_id', $secteurId);
        }

        return $requete;
    }
}
