<?php

namespace App\Http\Controllers;

use App\Models\Vicariat;
use Illuminate\Http\Request;

class ParametreController extends Controller
{
    public function edit()
    {
        return view('parametres.edit', ['vicariat' => Vicariat::courant()]);
    }

    public function update(Request $request)
    {
        Vicariat::courant()->update($request->validate([
            'nom' => 'required|string|max:120',
            'diocese' => 'nullable|string|max:150',
            'adresse' => 'nullable|string|max:255',
            'telephone' => 'nullable|string|max:60',
            'pied_extrait' => 'nullable|string|max:255',
        ]));

        return back()->with('succes', 'Paramètres enregistrés.');
    }
}
