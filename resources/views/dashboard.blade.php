@extends('layouts.app')
@section('title', 'Tableau de bord')

@section('styles')
    .barres{ display:flex; align-items:flex-end; gap:8px; height:150px; padding-top:10px; }
    .barres .col{ flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; height:100%; gap:4px; }
    .barres .b{ width:100%; max-width:46px; background:var(--vert-600); border-radius:4px 4px 0 0; min-height:2px; }
    .barres .col:last-child .b{ background:var(--ambre-500); }
    .barres .j{ font-size:11px; color:var(--doux); }
    .barres .m{ font-size:10.5px; color:var(--doux); font-variant-numeric:tabular-nums; white-space:nowrap; }
@endsection

@section('content')
<div class="entete">
    <h1>Tableau de bord</h1>
    <a href="{{ route('caisse.index') }}" class="btn btn-ambre">Ouvrir la caisse</a>
</div>

<h2>Aujourd'hui</h2>
<div class="grille">
    <div class="stat"><div class="lib">Ventes du jour</div><div class="val">{{ fcfa($aujourdhui['chiffre_affaires']) }}</div><div class="det">{{ $aujourdhui['nb_ventes'] }} vente(s)</div></div>
    <div class="stat"><div class="lib">Argent encaissé</div><div class="val">{{ fcfa($aujourdhui['encaisse']) }}</div><div class="det">ventes + remboursements</div></div>
    <div class="stat"><div class="lib">Marge du jour</div><div class="val positif">{{ fcfa($aujourdhui['marge']) }}</div></div>
    <div class="stat"><div class="lib">Crédits accordés</div><div class="val negatif">{{ fcfa($aujourdhui['credits_accordes']) }}</div></div>
</div>

<h2>Ce mois-ci</h2>
<div class="grille">
    <div class="stat"><div class="lib">Chiffre d'affaires</div><div class="val">{{ fcfa($mois['chiffre_affaires']) }}</div></div>
    <div class="stat"><div class="lib">Marge brute</div><div class="val">{{ fcfa($mois['marge']) }}</div></div>
    <div class="stat"><div class="lib">Dépenses</div><div class="val">{{ fcfa($mois['depenses']) }}</div><div class="det">hors achats de marchandise</div></div>
    <div class="stat"><div class="lib">Bénéfice net</div><div class="val {{ $mois['benefice'] >= 0 ? 'positif' : 'negatif' }}">{{ fcfa($mois['benefice']) }}</div></div>
</div>

<div class="deux-col">
    <div class="carte">
        <h2>Ventes des 7 derniers jours</h2>
        @php $max = max($jours->max('total'), 1); @endphp
        <div class="barres">
            @foreach($jours as $j)
                <div class="col" title="{{ $j['jour']->translatedFormat('l d/m') }} : {{ fcfa($j['total']) }}">
                    <div class="m">{{ $j['total'] ? number_format($j['total'] / 1000, 0, ',', ' ').'k' : '' }}</div>
                    <div class="b" style="height:{{ round($j['total'] / $max * 100) }}%"></div>
                    <div class="j">{{ $j['jour']->translatedFormat('D') }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="carte">
        <h2>À surveiller</h2>
        <p style="margin-bottom:10px">
            <a href="{{ route('clients.index', ['debiteurs' => 1]) }}"><b>{{ $nbDebiteurs }}</b> client(s) doivent <b>{{ fcfa($totalDettes) }}</b></a>
        </p>
        <p style="margin-bottom:8px"><a href="{{ route('produits.index', ['alerte' => 1]) }}"><b>{{ $nbStockBas }}</b> produit(s) en stock bas</a></p>
        @if($stockBas->isNotEmpty())
            <table>
                @foreach($stockBas as $p)
                    <tr>
                        <td><a href="{{ route('produits.show', $p) }}">{{ $p->nom }}</a></td>
                        <td class="n"><span class="badge {{ $p->stock <= 0 ? 'badge-rouge' : 'badge-ambre' }}">{{ $p->stock }} {{ $p->unite }}</span></td>
                        <td class="n"><a href="{{ route('stock.entree', ['produit' => $p->id]) }}" class="btn btn-leger btn-petit">Réappro.</a></td>
                    </tr>
                @endforeach
            </table>
        @endif
    </div>
</div>

<div class="carte tableau">
    <h2>Dernières ventes</h2>
    <table>
        <thead><tr><th>N°</th><th>Heure</th><th>Client</th><th>Vendeur</th><th class="n">Total</th><th></th></tr></thead>
        <tbody>
        @forelse($dernieresVentes as $v)
            <tr @class(['barre' => $v->estAnnulee()])>
                <td><a href="{{ route('ventes.show', $v) }}">{{ $v->numero }}</a></td>
                <td>{{ $v->created_at->format('d/m H:i') }}</td>
                <td>{{ $v->client?->nom ?? '—' }}</td>
                <td>{{ $v->user->name }}</td>
                <td class="n">{{ fcfa($v->total) }}</td>
                <td>@if($v->reste() > 0 && ! $v->estAnnulee())<span class="badge badge-ambre">crédit</span>@endif</td>
            </tr>
        @empty
            <tr><td colspan="6" class="vide">Pas encore de vente.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
