<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Produit;
use App\Models\Vente;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CaisseController extends Controller
{
    public function index()
    {
        $produits = Produit::with('categorie')->where('actif', true)->orderBy('nom')->get();
        $clients = Client::orderBy('nom')->get(['id', 'nom', 'telephone']);

        return view('caisse.index', compact('produits', 'clients'));
    }

    public function store(Request $request)
    {
        $donnees = $request->validate([
            'lignes' => 'required|array|min:1',
            'lignes.*.produit_id' => 'required|integer|exists:produits,id',
            'lignes.*.quantite' => 'required|integer|min:1|max:100000',
            'montant_paye' => 'required|integer|min:0',
            'mode_paiement' => ['required', Rule::in(array_keys(Vente::MODES))],
            'client_id' => 'nullable|integer|exists:clients,id',
        ], [
            'lignes.required' => 'Le panier est vide.',
        ]);

        $vente = Vente::enregistrer(
            $donnees['lignes'],
            (int) $donnees['montant_paye'],
            $donnees['mode_paiement'],
            $donnees['client_id'] ?? null,
            $request->user()->id,
        );

        return redirect()->route('ventes.ticket', $vente)->with('succes', "Vente {$vente->numero} enregistrée.");
    }
}
