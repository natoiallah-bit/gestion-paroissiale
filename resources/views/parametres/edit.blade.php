@extends('layouts.app')
@section('title', 'Paramètres')

@section('content')
<h1>Paramètres du vicariat</h1>

<form class="carte formulaire" method="POST" action="{{ route('parametres.update') }}">
    @csrf @method('PUT')
    <label for="nom">Nom du vicariat</label>
    <input type="text" id="nom" name="nom" value="{{ old('nom', $vicariat->nom) }}" required maxlength="120">
    <label for="diocese">Diocèse</label>
    <input type="text" id="diocese" name="diocese" value="{{ old('diocese', $vicariat->diocese) }}" maxlength="150">
    <label for="adresse">Adresse</label>
    <input type="text" id="adresse" name="adresse" value="{{ old('adresse', $vicariat->adresse) }}" maxlength="255">
    <label for="telephone">Téléphone</label>
    <input type="text" id="telephone" name="telephone" value="{{ old('telephone', $vicariat->telephone) }}" maxlength="60">
    <label for="pied_extrait">Mention en bas des extraits de registre</label>
    <input type="text" id="pied_extrait" name="pied_extrait" value="{{ old('pied_extrait', $vicariat->pied_extrait) }}" maxlength="255" placeholder="Pour copie conforme au registre">
    <button class="btn btn-ambre" style="margin-top:16px">Enregistrer</button>
</form>
@endsection
