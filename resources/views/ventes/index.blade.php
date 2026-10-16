@extends('layouts.app')
@section('title', 'Ventes')

@section('content')
<div class="entete">
    <h1>{{ auth()->user()->estGerant() ? 'Ventes' : 'Mes ventes' }}</h1>
    <a href="{{ route('caisse.index') }}" class="btn btn-ambre">Nouvelle vente</a>
</div>

<form class="filtres" method="GET">
    <div><label for="du">Du</label><input type="date" id="du" name="du" value="{{ $du->toDateString() }}"></div>
    <div><label for="au">Au</label><input type="date" id="au" name="au" value="{{ $au->toDateString() }}"></div>
    @if($vendeurs->isNotEmpty())
        <div>
            <label for="vendeur">Vendeur</label>
            <select name="vendeur" id="vendeur">
                <option value="">Tous</option>
                @foreach($vendeurs as $v)
                    <option value="{{ $v->id }}" @selected(request('vendeur') == $v->id)>{{ $v->name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <button class="btn">Afficher</button>
</form>

<div class="grille">
    <div class="stat"><div class="lib">Ventes validées</div><div class="val">{{ $ventes->where('statut', 'validee')->count() }}</div></div>
    <div class="stat"><div class="lib">Total vendu</div><div class="val">{{ fcfa($totalVentes) }}</div></div>
    <div class="stat"><div class="lib">Encaissé</div><div class="val">{{ fcfa($totalEncaisse) }}</div></div>
    <div class="stat"><div class="lib">Mis à crédit</div><div class="val negatif">{{ fcfa($totalVentes - $totalEncaisse) }}</div></div>
</div>

<div class="carte tableau">
    <table>
        <thead>
            <tr><th>N°</th><th>Date</th><th>Client</th><th>Vendeur</th><th class="n">Total</th><th class="n">Payé</th><th>État</th><th></th></tr>
        </thead>
        <tbody>
        @forelse($ventes as $v)
            <tr @class(['barre' => $v->estAnnulee()])>
                <td><a href="{{ route('ventes.show', $v) }}">{{ $v->numero }}</a></td>
                <td>{{ $v->created_at->format('d/m H:i') }}</td>
                <td>{{ $v->client?->nom ?? '—' }}</td>
                <td>{{ $v->user->name }}</td>
                <td class="n">{{ fcfa($v->total) }}</td>
                <td class="n">{{ fcfa($v->montant_paye) }}</td>
                <td>
                    @if($v->estAnnulee())<span class="badge badge-gris">Annulée</span>
                    @elseif($v->reste() > 0)<span class="badge badge-ambre">Crédit {{ fcfa($v->reste()) }}</span>
                    @else<span class="badge badge-vert">Payée</span>@endif
                </td>
                <td><a href="{{ route('ventes.ticket', $v) }}" class="btn btn-leger btn-petit">Ticket</a></td>
            </tr>
        @empty
            <tr><td colspan="8" class="vide">Aucune vente sur cette période.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
