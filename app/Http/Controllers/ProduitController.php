<?php

namespace App\Http\Controllers;

use App\Models\Categorie;
use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProduitController extends Controller
{
    public function index(Request $request)
    {
        $requete = Produit::with('categorie')->orderBy('nom');

        if ($request->filled('q')) {
            $q = $request->string('q');
            $requete->where(fn ($w) => $w->where('nom', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"));
        }
        if ($request->filled('categorie')) {
            $requete->where('categorie_id', $request->integer('categorie'));
        }
        if ($request->boolean('alerte')) {
            $requete->stockBas();
        }

        $produits = $requete->get();

        return view('produits.index', [
            'produits' => $produits,
            'categories' => Categorie::orderBy('nom')->get(),
            'valeurStock' => Produit::where('actif', true)->where('stock', '>', 0)->get()
                ->sum(fn ($p) => $p->stock * $p->prix_achat),
        ]);
    }

    public function create()
    {
        return view('produits.form', ['produit' => new Produit(['unite' => 'pièce', 'seuil_alerte' => 5, 'actif' => true]), 'categories' => Categorie::orderBy('nom')->get()]);
    }

    public function store(Request $request)
    {
        $donnees = $this->valider($request);
        $stockInitial = (int) ($request->validate(['stock_initial' => 'nullable|integer|min:0'])['stock_initial'] ?? 0);

        $produit = Produit::create($donnees);
        if ($stockInitial > 0) {
            $produit->mouvementer($stockInitial, 'entree', $request->user()->id, [
                'prix_achat_unitaire' => $produit->prix_achat,
                'note' => 'Stock initial',
            ]);
        }

        return redirect()->route('produits.index')->with('succes', "Produit « {$produit->nom} » ajouté.");
    }

    public function show(Produit $produit)
    {
        $produit->load('categorie');
        $mouvements = $produit->mouvements()->with('user', 'fournisseur', 'vente')->latest('id')->paginate(30);

        return view('produits.show', compact('produit', 'mouvements'));
    }

    public function edit(Produit $produit)
    {
        return view('produits.form', ['produit' => $produit, 'categories' => Categorie::orderBy('nom')->get()]);
    }

    public function update(Request $request, Produit $produit)
    {
        $produit->update($this->valider($request, $produit));

        return redirect()->route('produits.show', $produit)->with('succes', 'Produit modifié.');
    }

    public function destroy(Produit $produit)
    {
        // Un produit déjà vendu est gardé pour l'historique : on le désactive seulement
        if ($produit->mouvements()->where('type', 'vente')->exists()) {
            $produit->update(['actif' => false]);

            return redirect()->route('produits.index')->with('succes', "« {$produit->nom} » a déjà été vendu : il est désactivé (retiré de la caisse).");
        }

        $produit->delete();

        return redirect()->route('produits.index')->with('succes', 'Produit supprimé.');
    }

    private function valider(Request $request, ?Produit $produit = null): array
    {
        $donnees = $request->validate([
            'nom' => 'required|string|max:150',
            'code' => ['nullable', 'string', 'max:60', Rule::unique('produits', 'code')->ignore($produit?->id)],
            'categorie_id' => 'nullable|exists:categories,id',
            'unite' => 'required|string|max:30',
            'prix_achat' => 'required|integer|min:0',
            'prix_vente' => 'required|integer|min:0',
            'seuil_alerte' => 'required|integer|min:0',
        ]);
        $donnees['actif'] = $request->boolean('actif');

        return $donnees;
    }
}
