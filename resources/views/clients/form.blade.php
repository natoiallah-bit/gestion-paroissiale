@extends('layouts.app')
@section('title', $client->exists ? 'Modifier un client' : 'Nouveau client')

@section('content')
<h1>{{ $client->exists ? 'Modifier « '.$client->nom.' »' : 'Nouveau client' }}</h1>

<form class="carte formulaire" method="POST" action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}">
    @csrf
    @if($client->exists) @method('PUT') @endif
    <label for="nom">Nom</label>
    <input type="text" id="nom" name="nom" value="{{ old('nom', $client->nom) }}" required maxlength="150">
    <label for="telephone">Téléphone</label>
    <input type="tel" id="telephone" name="telephone" value="{{ old('telephone', $client->telephone) }}" maxlength="30">
    <label for="adresse">Adresse / quartier</label>
    <input type="text" id="adresse" name="adresse" value="{{ old('adresse', $client->adresse) }}" maxlength="255">
    <div class="actions" style="margin-top:16px">
        <button class="btn btn-ambre">Enregistrer</button>
        <a href="{{ $client->exists ? route('clients.show', $client) : route('clients.index') }}" class="btn btn-leger">Annuler</a>
    </div>
</form>
@endsection
