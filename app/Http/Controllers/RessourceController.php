<?php

namespace App\Http\Controllers;

use App\Models\Fiche;
use App\Models\Secteur;
use App\Models\Vicariat;
use App\Support\Enregistreur;
use App\Support\Portee;
use App\Support\Ressources;
use App\Support\Statistiques;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pages de toutes les fiches (secteurs, CEB, fidèles, sacrements…), pilotées par
 * App\Support\Ressources : une seule logique pour liste, formulaire, fiche et suppression.
 */
class RessourceController extends Controller
{
    private function def(Request $request, bool $ecriture = false): array
    {
        $cle = $request->route()->defaults['ressource'];
        $def = Ressources::get($cle);
        $permis = $ecriture ? Ressources::peutEcrire($request->user(), $cle) : Ressources::peutLire($request->user(), $cle);
        abort_unless($permis, 403, 'Votre compte n\'a pas accès à cette page.');

        return [$cle, $def];
    }

    private function relations(array $def): array
    {
        $r = [];
        foreach ($def['champs'] as $champ) {
            if ($champ['type'] === 'ref') {
                $r[] = Ressources::relation($champ);
            }
        }

        return $r;
    }

    private function fiche(Request $request, string $cle, int $id): Fiche
    {
        $fiche = Portee::restreindre(Ressources::get($cle)['modele']::query(), $request->user(), $cle)->findOrFail($id);

        return $fiche;
    }

    public function index(Request $request)
    {
        [$cle, $def] = $this->def($request);
        $user = $request->user();

        $requete = Portee::restreindre($def['modele']::query()->with($this->relations($def)), $user, $cle);

        if ($request->filled('q')) {
            $q = '%'.$request->string('q').'%';
            $requete->where(function ($w) use ($def, $q) {
                foreach ($def['recherche'] as $colonne) {
                    $w->orWhere($colonne, 'like', $q);
                }
            });
        }

        // Filtres : choix (statut, type, sens…), références (secteur, CEB…) et période
        $filtres = [];
        foreach ($def['champs'] as $champ) {
            if ($champ['type'] === 'choix' && ($champ['liste'] ?? false) && $request->filled($champ['nom'])) {
                $requete->where($champ['nom'], $request->string($champ['nom']));
            }
            if ($champ['type'] === 'ref' && in_array($champ['nom'], ['secteur_uuid', 'ceb_uuid', 'fidele_uuid'], true) && $request->filled($champ['nom'])) {
                $cible = Ressources::get($champ['ref'])['modele']::parUuid($request->string($champ['nom']));
                $requete->where(Ressources::colonne($champ), $cible?->id ?? 0);
            }
        }
        $dateChamp = collect($def['champs'])->first(fn ($c) => $c['type'] === 'date' && ($c['liste'] ?? false));
        if ($dateChamp && $request->filled('du')) {
            $requete->whereDate($dateChamp['nom'], '>=', $request->date('du'));
        }
        if ($dateChamp && $request->filled('au')) {
            $requete->whereDate($dateChamp['nom'], '<=', $request->date('au'));
        }

        $desc = str_starts_with($def['tri'], '-');
        $requete->orderBy(ltrim($def['tri'], '-'), $desc ? 'desc' : 'asc')->orderByDesc('id');

        $total = (clone $requete)->count();
        $resume = null;
        if ($cle === 'operations') {
            $resume = [
                'recettes' => (int) (clone $requete)->where('sens', 'recette')->sum('montant'),
                'depenses' => (int) (clone $requete)->where('sens', 'depense')->sum('montant'),
            ];
        }
        if ($cle === 'depots') {
            $resume = ['depose' => (int) (clone $requete)->sum('montant')];
        }
        if ($cle === 'mouvements') {
            $resume = ['membres' => (int) (clone $requete)->sum('nombre_membres')];
        }

        $fiches = $requete->paginate(30)->withQueryString();
        if ($cle === 'depots') {
            $cumuls = Statistiques::cumulsDepots();
            $fiches->getCollection()->each(fn ($d) => $d->forceFill($cumuls[$d->id] ?? []));
        }

        return view('ressources.index', [
            'cle' => $cle, 'def' => $def,
            'fiches' => $fiches,
            'total' => $total, 'resume' => $resume, 'dateChamp' => $dateChamp,
            'peutEcrire' => Ressources::peutEcrire($user, $cle),
            'options' => $this->optionsRefs($request, $def, ['secteur_uuid', 'ceb_uuid']),
        ]);
    }

    public function create(Request $request)
    {
        [$cle, $def] = $this->def($request, true);
        $fiche = new $def['modele'];
        foreach ($def['champs'] as $champ) {
            if (isset($champ['defaut'])) {
                $fiche->{$champ['nom']} = $champ['defaut'];
            }
        }
        // Raccourcis : « Ajouter un sacrement » depuis la fiche d'un fidèle, etc.
        $prefill = $request->only(array_column($def['champs'], 'nom'));

        return view('ressources.form', [
            'cle' => $cle, 'def' => $def, 'fiche' => $fiche, 'prefill' => $prefill,
            'options' => $this->optionsRefs($request, $def),
        ]);
    }

    public function store(Request $request)
    {
        [$cle, $def] = $this->def($request, true);
        $request->merge(Enregistreur::completer($cle, [], $request->user()));
        $valide = $request->validate(Ressources::regles($cle));
        $fiche = Enregistreur::enregistrer($cle, $valide, $request->user());

        $message = ucfirst($def['singulier']).' enregistré'.($def['article'] === 'une' ? 'e' : '').'.';
        if ($cle === 'depots') {
            // Les montants sont recalculés à chaque dépôt
            $cumul = Statistiques::cumulsDepots()[$fiche->id];
            $message .= ' Cumul déposé par ce secteur : '.fcfa($cumul['cumul_secteur']).'.'
                .($request->user()->estLimiteAUnSecteur() ? '' : ' Total reçu au vicariat : '.fcfa($cumul['total_vicariat']).'.');
        }

        return redirect()->route("$cle.show", $fiche)->with('succes', $message);
    }

    public function show(Request $request, int $id)
    {
        [$cle, $def] = $this->def($request);
        $fiche = $this->fiche($request, $cle, $id);
        $fiche->load($this->relations($def));

        $liees = [];
        foreach ($def['liees'] ?? [] as $lien) {
            if (! Ressources::peutLire($request->user(), $lien['ressource'])) {
                continue;
            }
            $defLiee = Ressources::get($lien['ressource']);
            $champ = Ressources::champ($lien['ressource'], $lien['cle']);
            $liees[] = $lien + [
                'def' => $defLiee,
                'fiches' => $defLiee['modele']::with($this->relations($defLiee))->where(Ressources::colonne($champ), $fiche->id)
                    ->orderBy(ltrim($defLiee['tri'], '-'), str_starts_with($defLiee['tri'], '-') ? 'desc' : 'asc')->limit(200)->get(),
            ];
        }

        if ($cle === 'depots') {
            $fiche->forceFill(Statistiques::cumulsDepots()[$fiche->id] ?? []);
        }

        return view('ressources.show', [
            'cle' => $cle, 'def' => $def, 'fiche' => $fiche, 'liees' => $liees,
            'peutModifier' => Portee::peutModifier($request->user(), $cle, $fiche),
            'vicariat' => Vicariat::courant(),
        ]);
    }

    public function edit(Request $request, int $id)
    {
        [$cle, $def] = $this->def($request, true);
        $fiche = $this->fiche($request, $cle, $id);
        abort_unless(Portee::peutModifier($request->user(), $cle, $fiche), 403);

        return view('ressources.form', [
            'cle' => $cle, 'def' => $def, 'fiche' => $fiche->load($this->relations($def)), 'prefill' => [],
            'options' => $this->optionsRefs($request, $def),
        ]);
    }

    public function update(Request $request, int $id)
    {
        [$cle, $def] = $this->def($request, true);
        $fiche = $this->fiche($request, $cle, $id);
        $request->merge(Enregistreur::completer($cle, [], $request->user()));
        $valide = $request->validate(Ressources::regles($cle));
        $fiche = Enregistreur::enregistrer($cle, $valide, $request->user(), $fiche);

        return redirect()->route("$cle.show", $fiche)->with('succes', 'Modifications enregistrées.');
    }

    public function destroy(Request $request, int $id)
    {
        [$cle, $def] = $this->def($request, true);
        $fiche = $this->fiche($request, $cle, $id);
        abort_unless(Portee::peutModifier($request->user(), $cle, $fiche), 403);

        if ($obstacle = $fiche->obstacleSuppression()) {
            return back()->withErrors(['suppression' => $obstacle]);
        }
        DB::transaction(function () use ($fiche) {
            $fiche->detacher();
            $fiche->delete();
        });

        return redirect()->route("$cle.index")->with('succes', 'Supprimé.');
    }

    /** Extrait de registre imprimable d'un sacrement */
    public function extrait(Request $request, int $id)
    {
        abort_unless(Ressources::peutLire($request->user(), 'sacrements'), 403);
        $sacrement = $this->fiche($request, 'sacrements', $id)->load('fidele.secteur', 'fidele.ceb', 'secteur');

        return view('ressources.extrait', ['sacrement' => $sacrement, 'vicariat' => Vicariat::courant()]);
    }

    /** Listes déroulantes des champs de type ref (limitées à ce que le compte peut voir) */
    private function optionsRefs(Request $request, array $def, ?array $seulement = null): array
    {
        $options = [];
        foreach ($def['champs'] as $champ) {
            if ($champ['type'] !== 'ref' || ($seulement && ! in_array($champ['nom'], $seulement, true))) {
                continue;
            }
            $cible = $champ['ref'];
            if (! Ressources::peutLire($request->user(), $cible)) {
                continue;
            }
            $defCible = Ressources::get($cible);
            $requete = Portee::restreindre($defCible['modele']::query(), $request->user(), $cible);
            $options[$champ['nom']] = $requete->with($this->relations($defCible))->orderBy($defCible['tri'][0] === '-' ? 'id' : $defCible['tri'])->limit(3000)->get()
                ->map(fn ($f) => ['uuid' => $f->uuid, 'texte' => $f->intitule().($cible === 'cebs' ? ' ('.($f->secteur?->nom ?? '').')' : ''), 'secteur' => $f->secteur?->uuid ?? null]);
        }

        return $options;
    }
}
