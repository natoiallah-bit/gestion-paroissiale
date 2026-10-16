<?php

namespace App\Http\Controllers;

use App\Models\Paroisse;
use App\Support\Statistiques;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $paroisseId = $user->estAdmin() ? $request->integer('paroisse') ?: null : null;

        return view('dashboard', Statistiques::tableauDeBord($user, $paroisseId) + [
            'paroisseChoisie' => $paroisseId ? Paroisse::find($paroisseId) : null,
            'listeParoisses' => $user->estAdmin() ? Paroisse::orderBy('nom')->get() : collect(),
        ]);
    }
}
