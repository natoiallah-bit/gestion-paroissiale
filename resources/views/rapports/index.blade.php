@extends('layouts.app')
@section('title', 'Rapports')

@section('content')
<div class="entete">
    <h1>Rapport du {{ $du->format('d/m/Y') }} au {{ $au->format('d/m/Y') }}</h1>
    <button type="button" class="btn btn-leger pas-imprimer" onclick="window.print()">Imprimer</button>
</div>

<form class="filtres pas-imprimer" method="GET">
    <div><label for="du">Du</label><input type="date" id="du" name="du" value="{{ $du->toDateString() }}"></div>
    <div><label for="au">Au</label><input type="date" id="au" name="au" value="{{ $au->toDateString() }}"></div>
    <button class="btn">Afficher</button>
    <a class="btn btn-leger" href="{{ route('rapports.index', ['du' => today()->toDateString(), 'au' => today()->toDateString()]) }}">Aujourd'hui</a>
    <a class="btn btn-leger" href="{{ route('rapports.index', ['du' => today()->startOfMonth()->toDateString(), 'au' => today()->toDateString()]) }}">Ce mois</a>
    <a class="btn btn-leger" href="{{ route('rapports.index', ['du' => today()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'au' => today()->subMonthNoOverflow()->endOfMonth()->toDateString()]) }}">Mois dernier</a>
</form>

<div class="deux-col">
    <div class="carte">
        <h2>Résultat</h2>
        <table>
            <tr><td>Chiffre d'affaires ({{ $bilan['nb_ventes'] }} ventes)</td><td class="n"><b>{{ fcfa($bilan['chiffre_affaires']) }}</b></td></tr>
            <tr><td>− Coût d'achat des articles vendus</td><td class="n">{{ fcfa($bilan['cout_achats']) }}</td></tr>
            <tr><td><b>= Marge brute</b></td><td class="n"><b>{{ fcfa($bilan['marge']) }}</b></td></tr>
            <tr><td>− Dépenses de fonctionnement</td><td class="n">{{ fcfa($bilan['depenses']) }}</td></tr>
            <tr><td><b>= Bénéfice net</b></td><td class="n {{ $bilan['benefice'] >= 0 ? 'positif' : 'negatif' }}"><b>{{ fcfa($bilan['benefice']) }}</b></td></tr>
        </table>
        <p class="aide" style="margin-top:8px">Les achats de marchandise ({{ fcfa($bilan['achats_marchandise']) }} sur la période) ne sont pas déduits ici : leur coût est compté quand les articles sont vendus.</p>
    </div>
    <div class="carte">
        <h2>Trésorerie</h2>
        <table>
            <tr><td>Encaissé sur les ventes</td><td class="n">{{ fcfa($bilan['encaisse'] - $bilan['remboursements']) }}</td></tr>
            <tr><td>+ Remboursements de crédits</td><td class="n">{{ fcfa($bilan['remboursements']) }}</td></tr>
            <tr><td><b>= Argent reçu</b></td><td class="n"><b>{{ fcfa($bilan['encaisse']) }}</b></td></tr>
            <tr><td>Achats de marchandise payés</td><td class="n">{{ fcfa($bilan['achats_marchandise']) }}</td></tr>
            <tr><td>Crédits accordés sur la période</td><td class="n negatif">{{ fcfa($bilan['credits_accordes']) }}</td></tr>
        </table>
        <h2 style="margin-top:16px">Par mode de paiement</h2>
        <table>
            @forelse($parMode as $m)
                <tr><td>{{ \App\Models\Vente::MODES[$m->mode_paiement] ?? $m->mode_paiement }} ({{ $m->nb }})</td><td class="n">{{ fcfa($m->encaisse) }}</td></tr>
            @empty
                <tr><td class="vide">—</td></tr>
            @endforelse
        </table>
    </div>
</div>

<div class="deux-col">
    <div class="carte tableau">
        <h2>Produits les plus vendus</h2>
        <table>
            <thead><tr><th>Produit</th><th class="n">Qté</th><th class="n">Ventes</th><th class="n">Marge</th></tr></thead>
            <tbody>
            @forelse($topProduits as $p)
                <tr><td>{{ $p->designation }}</td><td class="n">{{ $p->quantite }}</td><td class="n">{{ fcfa($p->chiffre) }}</td><td class="n">{{ fcfa($p->marge) }}</td></tr>
            @empty
                <tr><td colspan="4" class="vide">Aucune vente.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="carte tableau">
        <h2>Par vendeur</h2>
        <table>
            <thead><tr><th>Vendeur</th><th class="n">Ventes</th><th class="n">Total</th></tr></thead>
            <tbody>
            @forelse($parVendeur as $v)
                <tr><td>{{ $v->user?->name }}</td><td class="n">{{ $v->nb }}</td><td class="n">{{ fcfa($v->total) }}</td></tr>
            @empty
                <tr><td colspan="3" class="vide">—</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
