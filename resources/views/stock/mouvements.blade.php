@extends('layouts.app')
@section('title', 'Mouvements de stock')

@section('content')
<div class="entete">
    <h1>Mouvements de stock</h1>
    <a href="{{ route('stock.entree') }}" class="btn btn-ambre">Entrée de marchandise</a>
</div>

<form class="filtres" method="GET">
    <div>
        <label for="type">Type</label>
        <select name="type" id="type" onchange="this.form.submit()">
            <option value="">Tous</option>
            @foreach(\App\Models\MouvementStock::TYPES as $cle => $lib)
                <option value="{{ $cle }}" @selected(request('type') === $cle)>{{ $lib }}</option>
            @endforeach
        </select>
    </div>
</form>

<div class="carte tableau">
    <table>
        <thead><tr><th>Date</th><th>Produit</th><th>Type</th><th class="n">Quantité</th><th class="n">Stock après</th><th>Détail</th><th>Par</th></tr></thead>
        <tbody>
        @forelse($mouvements as $m)
            <tr>
                <td>{{ $m->created_at->format('d/m/Y H:i') }}</td>
                <td><a href="{{ route('produits.show', $m->produit) }}">{{ $m->produit->nom }}</a></td>
                <td>{{ $m->libelleType() }}</td>
                <td class="n {{ $m->quantite < 0 ? 'negatif' : 'positif' }}">{{ $m->quantite > 0 ? '+' : '' }}{{ $m->quantite }}</td>
                <td class="n">{{ $m->stock_apres }}</td>
                <td>
                    @if($m->vente)<a href="{{ route('ventes.show', $m->vente) }}">{{ $m->vente->numero }}</a>@endif
                    {{ $m->fournisseur?->nom }} {{ $m->note }}
                </td>
                <td>{{ $m->user->name }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="vide">Aucun mouvement.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $mouvements->links() }}
</div>
@endsection
