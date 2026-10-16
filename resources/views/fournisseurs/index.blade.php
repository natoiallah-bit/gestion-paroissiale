@extends('layouts.app')
@section('title', 'Fournisseurs')

@section('content')
<div class="entete">
    <h1>Fournisseurs</h1>
    <a href="{{ route('fournisseurs.create') }}" class="btn btn-ambre">+ Nouveau fournisseur</a>
</div>

<div class="carte tableau">
    <table>
        <thead><tr><th>Nom</th><th>Téléphone</th><th>Adresse</th><th class="n">Dépenses enregistrées</th><th></th></tr></thead>
        <tbody>
        @forelse($fournisseurs as $f)
            <tr>
                <td><b>{{ $f->nom }}</b>@if($f->note)<div class="aide">{{ $f->note }}</div>@endif</td>
                <td>{{ $f->telephone ?? '—' }}</td>
                <td>{{ $f->adresse ?? '—' }}</td>
                <td class="n">{{ fcfa($f->depenses_sum_montant) }}</td>
                <td class="actions">
                    <a href="{{ route('fournisseurs.edit', $f) }}" class="btn btn-leger btn-petit">Modifier</a>
                    <form method="POST" action="{{ route('fournisseurs.destroy', $f) }}" onsubmit="return confirm('Supprimer ce fournisseur ?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-leger btn-petit">Supprimer</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="vide">Aucun fournisseur.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
