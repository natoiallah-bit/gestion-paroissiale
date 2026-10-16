@extends('layouts.app')
@section('title', 'Catégories')

@section('content')
<h1>Catégories de produits</h1>

<form class="carte filtres" method="POST" action="{{ route('categories.store') }}">
    @csrf
    <div style="flex:1; min-width:200px"><label for="nom">Nouvelle catégorie</label><input type="text" id="nom" name="nom" required maxlength="80" placeholder="Boissons, Alimentation, Cosmétiques…"></div>
    <button class="btn btn-ambre">Ajouter</button>
</form>

<div class="carte tableau">
    <table>
        <thead><tr><th>Nom</th><th class="n">Produits</th><th></th></tr></thead>
        <tbody>
        @forelse($categories as $c)
            <tr>
                <td>
                    <form method="POST" action="{{ route('categories.update', $c) }}" class="actions">
                        @csrf @method('PUT')
                        <input type="text" name="nom" value="{{ $c->nom }}" required maxlength="80" style="max-width:260px">
                        <button class="btn btn-leger btn-petit">Renommer</button>
                    </form>
                </td>
                <td class="n"><a href="{{ route('produits.index', ['categorie' => $c->id]) }}">{{ $c->produits_count }}</a></td>
                <td>
                    <form method="POST" action="{{ route('categories.destroy', $c) }}" onsubmit="return confirm('Supprimer la catégorie ? Les produits resteront, sans catégorie.')">
                        @csrf @method('DELETE')
                        <button class="btn btn-leger btn-petit">Supprimer</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="3" class="vide">Aucune catégorie.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
