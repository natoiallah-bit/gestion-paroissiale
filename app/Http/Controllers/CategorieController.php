<?php

namespace App\Http\Controllers;

use App\Models\Categorie;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategorieController extends Controller
{
    public function index()
    {
        return view('categories.index', ['categories' => Categorie::withCount('produits')->orderBy('nom')->get()]);
    }

    public function store(Request $request)
    {
        Categorie::create($request->validate(['nom' => 'required|string|max:80|unique:categories,nom']));

        return back()->with('succes', 'Catégorie ajoutée.');
    }

    public function update(Request $request, Categorie $categorie)
    {
        $categorie->update($request->validate([
            'nom' => ['required', 'string', 'max:80', Rule::unique('categories', 'nom')->ignore($categorie->id)],
        ]));

        return back()->with('succes', 'Catégorie renommée.');
    }

    public function destroy(Categorie $categorie)
    {
        $categorie->delete(); // les produits restent, sans catégorie

        return back()->with('succes', 'Catégorie supprimée.');
    }
}
