@extends('layouts.app')
@section('title', 'Clients & crédits')

@section('content')
<div class="entete">
    <h1>Clients & crédits</h1>
    <a href="{{ route('clients.create') }}" class="btn btn-ambre">+ Nouveau client</a>
</div>

<form class="filtres" method="GET">
    <div style="flex:1; min-width:200px"><label for="q">Recherche</label><input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="Nom ou téléphone"></div>
    <label class="case"><input type="checkbox" name="debiteurs" value="1" @checked(request('debiteurs'))> Seulement ceux qui doivent</label>
    <button class="btn">Filtrer</button>
</form>

<div class="grille">
    <div class="stat"><div class="lib">Total des dettes clients</div><div class="val negatif">{{ fcfa($totalDettes) }}</div></div>
</div>

<div class="carte tableau">
    <table>
        <thead><tr><th>Client</th><th>Téléphone</th><th class="n">Doit</th><th></th></tr></thead>
        <tbody>
        @forelse($clients as $c)
            <tr>
                <td><a href="{{ route('clients.show', $c) }}"><b>{{ $c->nom }}</b></a></td>
                <td>{{ $c->telephone ?? '—' }}</td>
                <td class="n">
                    @if($c->dette > 0)<span class="badge badge-rouge">{{ fcfa($c->dette) }}</span>@else<span class="badge badge-vert">0 F</span>@endif
                </td>
                <td><a href="{{ route('clients.show', $c) }}" class="btn btn-leger btn-petit">Ouvrir</a></td>
            </tr>
        @empty
            <tr><td colspan="4" class="vide">Aucun client.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
