<?php

namespace App\Http\Controllers;

use App\Models\Vente;
use App\Models\VenteLigne;
use App\Support\Bilan;
use Illuminate\Http\Request;

class RapportController extends Controller
{
    public function index(Request $request)
    {
        $du = $request->date('du') ?? today()->startOfMonth();
        $au = $request->date('au') ?? today();
        if ($du->gt($au)) {
            [$du, $au] = [$au, $du];
        }

        $bilan = Bilan::periode($du, $au);

        $ventesPeriode = Vente::validees()->whereBetween('created_at', [$bilan['debut'], $bilan['fin']]);

        $topProduits = VenteLigne::whereIn('vente_id', (clone $ventesPeriode)->select('id'))
            ->selectRaw('produit_id, designation, SUM(quantite) as quantite, SUM(total) as chiffre, SUM(total - quantite * prix_achat_unitaire) as marge')
            ->groupBy('produit_id', 'designation')
            ->orderByDesc('chiffre')
            ->limit(15)
            ->get();

        $parMode = (clone $ventesPeriode)->selectRaw('mode_paiement, COUNT(*) as nb, SUM(montant_paye) as encaisse')
            ->groupBy('mode_paiement')->get();

        $parVendeur = (clone $ventesPeriode)->with('user')->selectRaw('user_id, COUNT(*) as nb, SUM(total) as total')
            ->groupBy('user_id')->get();

        return view('rapports.index', compact('du', 'au', 'bilan', 'topProduits', 'parMode', 'parVendeur'));
    }
}
