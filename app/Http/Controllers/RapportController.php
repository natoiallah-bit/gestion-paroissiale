<?php

namespace App\Http\Controllers;

use App\Models\Secteur;
use App\Support\Ressources;
use App\Support\Statistiques;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RapportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $donnees = $request->validate([
            'du' => 'nullable|date', 'au' => 'nullable|date|after_or_equal:du',
            'secteur' => 'nullable|integer', 'annee' => 'nullable|integer|min:1900|max:2200',
        ]);

        $du = Carbon::parse($donnees['du'] ?? today()->startOfYear());
        $au = Carbon::parse($donnees['au'] ?? today());
        $annee = (int) ($donnees['annee'] ?? today()->year);
        $secteurId = $user->estLimiteAUnSecteur() ? $user->secteur_id : ($donnees['secteur'] ?? null);

        return view('rapports.index', [
            'du' => $du, 'au' => $au, 'annee' => $annee,
            'secteurId' => $secteurId,
            'secteurs' => $user->estLimiteAUnSecteur() ? collect() : Secteur::orderBy('nom')->get(),
            'finances' => Ressources::peutLire($user, 'operations') ? Statistiques::bilan($user, $du, $au, $secteurId) : null,
            'sacrements' => Ressources::peutLire($user, 'sacrements') ? Statistiques::sacrementsParAnnee($user, $annee, $secteurId) : null,
            'effectifs' => Ressources::peutLire($user, 'fideles') ? Statistiques::tableauDeBord($user, $secteurId)['resume'] : null,
            'comptes' => Ressources::peutLire($user, 'operations') ? Statistiques::comptes($user, $secteurId) : null,
            'parSecteur' => $user->estAdmin() && ! $secteurId ? Statistiques::parSecteur($user) : null,
        ]);
    }
}
