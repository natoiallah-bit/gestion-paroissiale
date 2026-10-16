@extends('layouts.app')
@section('title', 'Produits')

@section('content')
<div class="entete">
    <h1>Produits</h1>
    <div class="actions">
        <a href="{{ route('stock.entree') }}" class="btn btn-leger">Entrée de marchandise</a>
        <a href="{{ route('produits.create') }}" class="btn btn-ambre">+ Nouveau produit</a>
    </div>
</div>

<form class="filtres" method="GET">
    <div style="flex:1; min-width:200px"><label for="q">Recherche</label><input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="Nom ou code"></div>
    <div>
        <label for="categorie">Catégorie</label>
        <select name="categorie" id="categorie">
            <option value="">Toutes</option>
            @foreach($categories as $c)
                <option value="{{ $c->id }}" @selected(request('categorie') == $c->id)>{{ $c->nom }}</option>
            @endforeach
        </select>
    </div>
    <label class="case"><input type="checkbox" name="alerte" value="1" @checked(request('alerte'))> Stock bas seulement</label>
    <button class="btn">Filtrer</button>
</form>

<p class="aide" style="margin-bottom:10px">Valeur du stock (au prix d'achat) : <b>{{ fcfa($valeurStock) }}</b></p>

<div class="carte tableau">
    <table>
        <thead><tr><th>Produit</th><th>Catégorie</th><th class="n">Prix d'achat</th><th class="n">Prix de vente</th><th class="n">Marge</th><th class="n">Stock</th><th></th></tr></thead>
        <tbody>
        @forelse($produits as $p)
            <tr>
                <td>
                    <a href="{{ route('produits.show', $p) }}"><b>{{ $p->nom }}</b></a>
                    @if($p->code)<div class="aide">{{ $p->code }}</div>@endif
                    @unless($p->actif)<span class="badge badge-gris">Inactif</span>@endunless
                </td>
                <td>{{ $p->categorie?->nom ?? '—' }}</td>
                <td class="n">{{ fcfa($p->prix_achat) }}</td>
                <td class="n">{{ fcfa($p->prix_vente) }}</td>
                <td class="n {{ $p->prix_vente < $p->prix_achat ? 'negatif' : '' }}">{{ fcfa($p->prix_vente - $p->prix_achat) }}</td>
                <td class="n">
                    <span class="badge {{ $p->stock <= 0 ? 'badge-rouge' : ($p->estEnAlerte() ? 'badge-ambre' : 'badge-vert') }}">{{ $p->stock }} {{ $p->unite }}</span>
                </td>
                <td class="actions">
                    <a href="{{ route('produits.edit', $p) }}" class="btn btn-leger btn-petit">Modifier</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="vide">Aucun produit. <a href="{{ route('produits.create') }}">Ajouter le premier</a>.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
