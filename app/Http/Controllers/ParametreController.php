<?php

namespace App\Http\Controllers;

use App\Models\Boutique;
use Illuminate\Http\Request;

class ParametreController extends Controller
{
    public function edit()
    {
        return view('parametres.edit', ['boutique' => Boutique::courante()]);
    }

    public function update(Request $request)
    {
        Boutique::courante()->update($request->validate([
            'nom' => 'required|string|max:120',
            'adresse' => 'nullable|string|max:255',
            'telephone' => 'nullable|string|max:60',
            'pied_ticket' => 'nullable|string|max:255',
        ]));

        return back()->with('succes', 'Paramètres enregistrés.');
    }
}
