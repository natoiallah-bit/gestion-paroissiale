@extends('layouts.app')
@section('title', $produit->exists ? 'Modifier un produit' : 'Nouveau produit')

@section('content')
<h1>{{ $produit->exists ? 'Modifier « '.$produit->nom.' »' : 'Nouveau produit' }}</h1>

<form class="carte formulaire" method="POST" action="{{ $produit->exists ? route('produits.update', $produit) : route('produits.store') }}">
    @csrf
    @if($produit->exists) @method('PUT') @endif

    <label for="nom">Nom du produit</label>
    <input type="text" id="nom" name="nom" value="{{ old('nom', $produit->nom) }}" required maxlength="150">

    <div class="ligne-champs">
        <div>
            <label for="code">Code / code-barres <span class="aide">(facultatif)</span></label>
            <input type="text" id="code" name="code" value="{{ old('code', $produit->code) }}" maxlength="60">
        </div>
        <div>
            <label for="categorie_id">Catégorie</label>
            <select id="categorie_id" name="categorie_id">
                <option value="">— Aucune —</option>
                @foreach($categories as $c)
                    <option value="{{ $c->id }}" @selected(old('categorie_id', $produit->categorie_id) == $c->id)>{{ $c->nom }}</option>
                @endforeach
            </select>
            <div class="aide"><a href="{{ route('categories.index') }}">Gérer les catégories</a></div>
        </div>
    </div>

    <div class="ligne-champs">
        <div>
            <label for="prix_achat">Prix d'achat (F)</label>
            <input type="number" id="prix_achat" name="prix_achat" min="0" step="1" value="{{ old('prix_achat', $produit->prix_achat ?? 0) }}" required>
        </div>
        <div>
            <label for="prix_vente">Prix de vente (F)</label>
            <input type="number" id="prix_vente" name="prix_vente" min="0" step="1" value="{{ old('prix_vente', $produit->prix_vente) }}" required>
        </div>
    </div>

    <div class="ligne-champs">
        <div>
            <label for="unite">Unité</label>
            <input type="text" id="unite" name="unite" value="{{ old('unite', $produit->unite) }}" list="unites" required maxlength="30">
            <datalist id="unites"><option value="pièce"><option value="kg"><option value="litre"><option value="sac"><option value="carton"><option value="paquet"><option value="boîte"></datalist>
        </div>
        <div>
            <label for="seuil_alerte">Alerte quand le stock descend à</label>
            <input type="number" id="seuil_alerte" name="seuil_alerte" min="0" value="{{ old('seuil_alerte', $produit->seuil_alerte) }}" required>
        </div>
        @unless($produit->exists)
            <div>
                <label for="stock_initial">Stock de départ</label>
                <input type="number" id="stock_initial" name="stock_initial" min="0" value="{{ old('stock_initial', 0) }}">
            </div>
        @endunless
    </div>

    <label class="case" style="margin-top:14px">
        <input type="checkbox" name="actif" value="1" @checked(old('actif', $produit->actif ?? true))> Produit en vente (visible à la caisse)
    </label>

    @if($produit->exists)
        <p class="aide">Le stock ne se modifie pas ici : utilisez « Entrée de marchandise » ou « Ajuster le stock » sur la fiche du produit.</p>
    @endif

    <div class="actions" style="margin-top:16px">
        <button class="btn btn-ambre">Enregistrer</button>
        <a href="{{ $produit->exists ? route('produits.show', $produit) : route('produits.index') }}" class="btn btn-leger">Annuler</a>
    </div>
</form>
@endsection
