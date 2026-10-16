@extends('layouts.app')
@section('title', $user->exists ? 'Modifier un compte' : 'Nouveau compte')

@section('content')
<h1>{{ $user->exists ? 'Modifier le compte de '.$user->name : 'Nouveau compte' }}</h1>

<form class="carte formulaire" method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}">
    @csrf
    @if($user->exists) @method('PUT') @endif
    <label for="name">Nom</label>
    <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="120">
    <label for="email">E-mail (identifiant de connexion)</label>
    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="150">
    <label for="role">Rôle</label>
    <select id="role" name="role">
        @foreach(\App\Models\User::ROLES as $cle => $lib)<option value="{{ $cle }}" @selected(old('role', $user->role) === $cle)>{{ $lib }}</option>@endforeach
    </select>
    <label for="secteur_id">Secteur du compte</label>
    <select id="secteur_id" name="secteur_id">
        <option value="">— Tout le vicariat (administrateur, ou trésorier du conseil économique du vicariat)</option>
        @foreach($secteurs as $p)<option value="{{ $p->id }}" @selected((int) old('secteur_id', $user->secteur_id) === $p->id)>{{ $p->nom }}</option>@endforeach
    </select>
    <div class="aide">Le secrétaire et le trésorier d'un secteur ne voient que leur secteur. Un trésorier sans secteur gère la caisse centrale du vicariat.</div>
    <div class="ligne-champs">
        <div><label for="password">Mot de passe @if($user->exists)<span class="aide">(vide = inchangé)</span>@endif</label><input type="password" id="password" name="password" autocomplete="new-password" @required(! $user->exists)></div>
        <div><label for="password_confirmation">Confirmation</label><input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password"></div>
    </div>
    <label class="case" style="margin-top:14px"><input type="checkbox" name="actif" value="1" @checked(old('actif', $user->actif ?? true))> Compte actif</label>
    <div class="actions" style="margin-top:16px">
        <button class="btn btn-ambre">Enregistrer</button>
        <a href="{{ route('users.index') }}" class="btn btn-leger">Annuler</a>
    </div>
</form>
@endsection
