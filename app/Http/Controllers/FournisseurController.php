<?php

namespace App\Http\Controllers;

use App\Models\Fournisseur;
use Illuminate\Http\Request;

class FournisseurController extends Controller
{
    public function index()
    {
        $fournisseurs = Fournisseur::withSum('depenses', 'montant')->orderBy('nom')->get();

        return view('fournisseurs.index', compact('fournisseurs'));
    }

    public function create()
    {
        return view('fournisseurs.form', ['fournisseur' => new Fournisseur]);
    }

    public function store(Request $request)
    {
        Fournisseur::create($this->valider($request));

        return redirect()->route('fournisseurs.index')->with('succes', 'Fournisseur ajouté.');
    }

    public function edit(Fournisseur $fournisseur)
    {
        return view('fournisseurs.form', compact('fournisseur'));
    }

    public function update(Request $request, Fournisseur $fournisseur)
    {
        $fournisseur->update($this->valider($request));

        return redirect()->route('fournisseurs.index')->with('succes', 'Fournisseur modifié.');
    }

    public function destroy(Fournisseur $fournisseur)
    {
        $fournisseur->delete();

        return redirect()->route('fournisseurs.index')->with('succes', 'Fournisseur supprimé.');
    }

    private function valider(Request $request): array
    {
        return $request->validate([
            'nom' => 'required|string|max:150',
            'telephone' => 'nullable|string|max:30',
            'adresse' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
        ]);
    }
}
