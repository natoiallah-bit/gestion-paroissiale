<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Produit;
use App\Models\Vente;
use App\Support\Bilan;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->user()->estGerant()) {
            return redirect()->route('caisse.index');
        }

        $aujourdhui = Bilan::periode(today(), today());
        $mois = Bilan::periode(today()->startOfMonth(), today());

        // Ventes des 7 derniers jours, pour le petit graphique
        $jours = collect(range(6, 0))->map(function ($decalage) {
            $jour = today()->subDays($decalage);

            return [
                'jour' => $jour,
                'total' => (int) Vente::validees()->whereDate('created_at', $jour)->sum('total'),
            ];
        });

        $stockBas = Produit::stockBas()->orderBy('stock')->limit(10)->get();

        $dettes = Client::query()->select('clients.*')->avecDette()->get()->where('dette', '>', 0);

        $dernieresVentes = Vente::with('client', 'user')->latest()->limit(8)->get();

        return view('dashboard', [
            'aujourdhui' => $aujourdhui,
            'mois' => $mois,
            'jours' => $jours,
            'stockBas' => $stockBas,
            'nbStockBas' => Produit::stockBas()->count(),
            'totalDettes' => (int) $dettes->sum('dette'),
            'nbDebiteurs' => $dettes->count(),
            'dernieresVentes' => $dernieresVentes,
        ]);
    }
}
