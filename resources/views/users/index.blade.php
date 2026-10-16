@extends('layouts.app')
@section('title', 'Utilisateurs')

@section('content')
<div class="entete">
    <h1>Utilisateurs</h1>
    <a href="{{ route('users.create') }}" class="btn btn-ambre">+ Nouveau compte</a>
</div>

<div class="carte tableau">
    <table>
        <thead><tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th>État</th><th></th></tr></thead>
        <tbody>
        @foreach($users as $u)
            <tr>
                <td><b>{{ $u->name }}</b></td>
                <td>{{ $u->email }}</td>
                <td>{{ $u->libelleRole() }}</td>
                <td>@if($u->actif)<span class="badge badge-vert">Actif</span>@else<span class="badge badge-gris">Désactivé</span>@endif</td>
                <td><a href="{{ route('users.edit', $u) }}" class="btn btn-leger btn-petit">Modifier</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<p class="aide">Le <b>gérant</b> a accès à tout. Le <b>vendeur</b> a accès à la caisse, à ses propres ventes et aux clients (enregistrer un remboursement).</p>
@endsection
