@extends('layouts.app')
@section('title', 'Paramètres')

@section('content')
<h1>Paramètres de la boutique</h1>

<form class="carte formulaire" method="POST" action="{{ route('parametres.update') }}">
    @csrf @method('PUT')
    <label for="nom">Nom de la boutique</label>
    <input type="text" id="nom" name="nom" value="{{ old('nom', $boutique->nom) }}" required maxlength="120">
    <label for="adresse">Adresse</label>
    <input type="text" id="adresse" name="adresse" value="{{ old('adresse', $boutique->adresse) }}" maxlength="255">
    <label for="telephone">Téléphone</label>
    <input type="text" id="telephone" name="telephone" value="{{ old('telephone', $boutique->telephone) }}" maxlength="60">
    <label for="pied_ticket">Message en bas du ticket</label>
    <input type="text" id="pied_ticket" name="pied_ticket" value="{{ old('pied_ticket', $boutique->pied_ticket) }}" maxlength="255" placeholder="Merci de votre visite !">
    <button class="btn btn-ambre" style="margin-top:16px">Enregistrer</button>
</form>
@endsection
