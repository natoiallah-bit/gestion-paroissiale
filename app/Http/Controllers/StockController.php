<?php

namespace App\Http\Controllers;

use App\Models\Depense;
use App\Models\Fournisseur;
use App\Models\MouvementStock;
use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function entreeForm(Request $request)
    {
        return view('stock.entree', [
            'produits' => Produit::where('actif', true)->orderBy('nom')->get(),
            'fournisseurs' => Fournisseur::orderBy('nom')->get(),
            'produitChoisi' => $request->integer('produit') ?: null,
        ]);
    }

    // Réception de marchandise : une ou plusieurs lignes, en option enregistrée comme dépense
    public function entree(Request $request)
    {
        $donnees = $request->validate([
            'fournisseur_id' => 'nullable|exists:fournisseurs,id',
            'note' => 'nullable|string|max:255',
            'lignes' => 'required|array|min:1',
            'lignes.*.produit_id' => 'required|integer|exists:produits,id',
            'lignes.*.quantite' => 'required|integer|min:1',
            'lignes.*.prix_achat' => 'required|integer|min:0',
            'enregistrer_depense' => 'nullable|boolean',
            'maj_prix_achat' => 'nullable|boolean',
        ], ['lignes.required' => 'Ajoutez au moins un produit.']);

        $userId = $request->user()->id;
        $total = 0;

        DB::transaction(function () use ($donnees, $request, $userId, &$total) {
            foreach ($donnees['lignes'] as $ligne) {
                $produit = Produit::whereKey($ligne['produit_id'])->lockForUpdate()->firstOrFail();
                if ($request->boolean('maj_prix_achat')) {
                    $produit->prix_achat = (int) $ligne['prix_achat'];
                }
                $produit->mouvementer((int) $ligne['quantite'], 'entree', $userId, [
                    'prix_achat_unitaire' => (int) $ligne['prix_achat'],
                    'fournisseur_id' => $donnees['fournisseur_id'] ?? null,
                    'note' => $donnees['note'] ?? null,
                ]);
                $total += (int) $ligne['quantite'] * (int) $ligne['prix_achat'];
            }

            if ($request->boolean('enregistrer_depense') && $total > 0) {
                Depense::create([
                    'libelle' => 'Achat de marchandise'.(isset($donnees['note']) ? ' — '.$donnees['note'] : ''),
                    'categorie' => Depense::ACHAT_MARCHANDISE,
                    'montant' => $total,
                    'date_depense' => today(),
                    'fournisseur_id' => $donnees['fournisseur_id'] ?? null,
                    'user_id' => $userId,
                ]);
            }
        });

        return redirect()->route('stock.mouvements')->with('succes', 'Entrée de stock enregistrée ('.fcfa($total).').');
    }

    // Correction après inventaire, casse, perte… : on saisit le stock réellement compté
    public function ajuster(Request $request, Produit $produit)
    {
        $donnees = $request->validate([
            'stock_reel' => 'required|integer|min:0',
            'note' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($produit, $donnees, $request) {
            $produit = Produit::whereKey($produit->id)->lockForUpdate()->firstOrFail();
            $ecart = (int) $donnees['stock_reel'] - $produit->stock;
            if ($ecart !== 0) {
                $produit->mouvementer($ecart, 'ajustement', $request->user()->id, ['note' => $donnees['note']]);
            }
        });

        return redirect()->route('produits.show', $produit)->with('succes', 'Stock ajusté.');
    }

    public function mouvements(Request $request)
    {
        $requete = MouvementStock::with('produit', 'user', 'fournisseur', 'vente')->latest('id');
        if ($request->filled('type')) {
            $requete->where('type', $request->string('type'));
        }

        return view('stock.mouvements', ['mouvements' => $requete->paginate(40)->withQueryString()]);
    }
}
