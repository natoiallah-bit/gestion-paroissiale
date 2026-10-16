@extends('layouts.app')
@section('title', $fournisseur->exists ? 'Modifier un fournisseur' : 'Nouveau fournisseur')

@section('content')
<h1>{{ $fournisseur->exists ? 'Modifier « '.$fournisseur->nom.' »' : 'Nouveau fournisseur' }}</h1>

<form class="carte formulaire" method="POST" action="{{ $fournisseur->exists ? route('fournisseurs.update', $fournisseur) : route('fournisseurs.store') }}">
    @csrf
    @if($fournisseur->exists) @method('PUT') @endif
    <label for="nom">Nom</label>
    <input type="text" id="nom" name="nom" value="{{ old('nom', $fournisseur->nom) }}" required maxlength="150">
    <label for="telephone">Téléphone</label>
    <input type="tel" id="telephone" name="telephone" value="{{ old('telephone', $fournisseur->telephone) }}" maxlength="30">
    <label for="adresse">Adresse</label>
    <input type="text" id="adresse" name="adresse" value="{{ old('adresse', $fournisseur->adresse) }}" maxlength="255">
    <label for="note">Note</label>
    <textarea id="note" name="note" rows="3" maxlength="1000">{{ old('note', $fournisseur->note) }}</textarea>
    <div class="actions" style="margin-top:16px">
        <button class="btn btn-ambre">Enregistrer</button>
        <a href="{{ route('fournisseurs.index') }}" class="btn btn-leger">Annuler</a>
    </div>
</form>
@endsection
