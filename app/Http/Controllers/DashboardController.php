<?php

namespace App\Http\Controllers;

use App\Models\Secteur;
use App\Support\Statistiques;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $secteurId = $user->estAdmin() ? $request->integer('secteur') ?: null : null;

        return view('dashboard', Statistiques::tableauDeBord($user, $secteurId) + [
            'secteurChoisie' => $secteurId ? Secteur::find($secteurId) : null,
            'listeSecteurs' => $user->estAdmin() ? Secteur::orderBy('nom')->get() : collect(),
        ]);
    }
}
