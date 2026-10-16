<?php

namespace App\Http\Controllers;

use App\Models\Depense;
use App\Models\Fournisseur;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepenseController extends Controller
{
    public function index(Request $request)
    {
        $du = $request->date('du') ?? today()->startOfMonth();
        $au = $request->date('au') ?? today();

        $depenses = Depense::with('fournisseur', 'user')
            ->whereDate('date_depense', '>=', $du)->whereDate('date_depense', '<=', $au)
            ->orderByDesc('date_depense')->orderByDesc('id')->get();

        return view('depenses.index', [
            'depenses' => $depenses,
            'du' => $du,
            'au' => $au,
            'parCategorie' => $depenses->groupBy('categorie')->map->sum('montant')->sortDesc(),
            'fournisseurs' => Fournisseur::orderBy('nom')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $donnees = $request->validate([
            'libelle' => 'required|string|max:200',
            'categorie' => ['required', Rule::in(Depense::CATEGORIES)],
            'montant' => 'required|integer|min:1',
            'date_depense' => 'required|date|before_or_equal:today',
            'fournisseur_id' => 'nullable|exists:fournisseurs,id',
        ]);

        Depense::create($donnees + ['user_id' => $request->user()->id]);

        return back()->with('succes', 'Dépense enregistrée.');
    }

    public function destroy(Depense $depense)
    {
        $depense->delete();

        return back()->with('succes', 'Dépense supprimée.');
    }
}
