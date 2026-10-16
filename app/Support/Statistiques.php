<?php

namespace App\Support;

use App\Models\Ceb;
use App\Models\Fidele;
use App\Models\Mouvement;
use App\Models\Operation;
use App\Models\Paroisse;
use App\Models\Sacrement;
use App\Models\User;
use Carbon\CarbonInterface;

/** Chiffres du tableau de bord et des rapports, limités à ce que le compte a le droit de voir. */
class Statistiques
{
    public static function tableauDeBord(User $user, ?int $paroisseId = null): array
    {
        $vue = fn (string $cle, ?int $p = null) => self::portee($cle, $user, $p ?? $paroisseId);

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
            ? ['mois' => self::bilan($user, today()->startOfMonth(), today(), $paroisseId),
                'annee' => self::bilan($user, today()->startOfYear(), today(), $paroisseId)]
            : null;

        return compact('resume', 'parType', 'activites', 'annonces', 'finances') + [
            'paroisses' => $user->estAdmin() && ! $paroisseId ? self::parParoisse($user) : null,
        ];
    }

    /** Recettes, dépenses et solde d'une période (dates incluses), détaillés par catégorie. */
    public static function bilan(User $user, CarbonInterface $debut, CarbonInterface $fin, ?int $paroisseId = null): array
    {
        $ops = self::portee('operations', $user, $paroisseId)
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

    /** Vue du vicariat : une ligne par paroisse */
    public static function parParoisse(User $user): array
    {
        $lignes = [];
        foreach (Paroisse::orderBy('nom')->get() as $p) {
            $fideles = Fidele::where('paroisse_id', $p->id)->where('statut', 'actif');
            $lignes[] = [
                'paroisse' => $p,
                'fideles' => (clone $fideles)->count(),
                'cebs' => Ceb::where('paroisse_id', $p->id)->count(),
                'mouvements' => Mouvement::where('paroisse_id', $p->id)->count(),
                'sacrements' => Sacrement::where('paroisse_id', $p->id)->whereYear('date_sacrement', today()->year)->count(),
                'recettes' => (int) Operation::where('paroisse_id', $p->id)->where('sens', 'recette')->whereYear('date_operation', today()->year)->sum('montant'),
            ];
        }

        return $lignes;
    }

    /** Sacrements d'une année par type et par mois (registre statistique) */
    public static function sacrementsParAnnee(User $user, int $annee, ?int $paroisseId = null): array
    {
        $lignes = self::portee('sacrements', $user, $paroisseId)->whereYear('date_sacrement', $annee)
            ->selectRaw('type, count(*) as n')->groupBy('type')->pluck('n', 'type');

        return collect(Ressources::SACREMENTS)->map(fn ($lib, $type) => ['type' => $type, 'libelle' => $lib, 'n' => (int) ($lignes[$type] ?? 0)])->values()->all();
    }

    private static function portee(string $cle, User $user, ?int $paroisseId)
    {
        $requete = Portee::restreindre(Ressources::get($cle)['modele']::query(), $user, $cle);
        if ($paroisseId && $cle !== 'paroisses') {
            $requete->where('paroisse_id', $paroisseId);
        }

        return $requete;
    }
}
