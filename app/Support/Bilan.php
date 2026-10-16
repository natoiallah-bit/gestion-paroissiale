<?php

namespace App\Support;

use App\Models\Depense;
use App\Models\Remboursement;
use App\Models\Vente;
use App\Models\VenteLigne;
use Carbon\CarbonInterface;

class Bilan
{
    /**
     * Chiffres d'une période (dates incluses).
     *
     * - chiffre_affaires : total des ventes validées
     * - cout_achats      : prix d'achat des articles vendus
     * - marge            : chiffre d'affaires - coût d'achat
     * - depenses         : dépenses de fonctionnement (loyer, transport…)
     * - achats_marchandise : dépenses « Achat marchandise » ; non déduites du bénéfice,
     *                      car leur coût est déjà compté dans cout_achats au moment de la vente
     * - benefice         : marge - dépenses
     * - encaisse         : argent réellement reçu (ventes + remboursements de crédits)
     */
    public static function periode(CarbonInterface $debut, CarbonInterface $fin): array
    {
        $debut = $debut->copy()->startOfDay();
        $fin = $fin->copy()->endOfDay();

        $ventes = Vente::validees()->whereBetween('created_at', [$debut, $fin]);

        $chiffreAffaires = (int) (clone $ventes)->sum('total');
        $payeAuComptant = (int) (clone $ventes)->sum('montant_paye');
        $nbVentes = (clone $ventes)->count();

        $coutAchats = (int) VenteLigne::whereIn('vente_id', (clone $ventes)->select('id'))
            ->selectRaw('COALESCE(SUM(quantite * prix_achat_unitaire), 0) as cout')
            ->value('cout');

        $depensesPeriode = Depense::whereDate('date_depense', '>=', $debut)->whereDate('date_depense', '<=', $fin);
        $achatsMarchandise = (int) (clone $depensesPeriode)->where('categorie', Depense::ACHAT_MARCHANDISE)->sum('montant');
        $depenses = (int) (clone $depensesPeriode)->where('categorie', '!=', Depense::ACHAT_MARCHANDISE)->sum('montant');
        $remboursements = (int) Remboursement::whereBetween('created_at', [$debut, $fin])->sum('montant');

        $marge = $chiffreAffaires - $coutAchats;

        return [
            'debut' => $debut,
            'fin' => $fin,
            'nb_ventes' => $nbVentes,
            'chiffre_affaires' => $chiffreAffaires,
            'cout_achats' => $coutAchats,
            'marge' => $marge,
            'depenses' => $depenses,
            'achats_marchandise' => $achatsMarchandise,
            'benefice' => $marge - $depenses,
            'credits_accordes' => $chiffreAffaires - $payeAuComptant,
            'remboursements' => $remboursements,
            'encaisse' => $payeAuComptant + $remboursements,
        ];
    }
}
