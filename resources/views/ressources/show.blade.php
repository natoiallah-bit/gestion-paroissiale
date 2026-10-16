@extends('layouts.app')
@section('title', $fiche->intitule())

@section('content')
<div class="entete">
    <h1>{{ $fiche->intitule() }}</h1>
    <div class="actions">
        @if($cle === 'sacrements')<a href="{{ route('sacrements.extrait', $fiche) }}" class="btn" target="_blank">Extrait à imprimer</a>@endif
        @if($cle === 'fideles' && \App\Support\Ressources::peutEcrire(auth()->user(), 'sacrements'))
            <a href="{{ route('sacrements.create', ['fidele_uuid' => $fiche->uuid]) }}" class="btn btn-ambre">+ Sacrement</a>
        @endif
        @if($peutModifier)
            <a href="{{ route("$cle.edit", $fiche) }}" class="btn btn-leger">Modifier</a>
            <form method="POST" action="{{ route("$cle.destroy", $fiche) }}" onsubmit="return confirm('Supprimer définitivement cette fiche ?')">
                @csrf @method('DELETE')
                <button class="btn btn-danger">Supprimer</button>
            </form>
        @endif
    </div>
</div>

<div class="carte">
    <table>
        @foreach($def['champs'] as $champ)
            <tr>
                <th style="width:220px">{{ $champ['label'] }}</th>
                <td>
                    @if($champ['type'] === 'ref' && $fiche->{\App\Support\Ressources::relation($champ)} && \App\Support\Ressources::peutLire(auth()->user(), $champ['ref']))
                        <a href="{{ route($champ['ref'].'.show', $fiche->{\App\Support\Ressources::relation($champ)}) }}">{{ champ_valeur($fiche, $champ, false) }}</a>
                    @else
                        {!! nl2br(e(champ_valeur($fiche, $champ, false))) !!}
                    @endif
                </td>
            </tr>
        @endforeach
        @if($cle === 'fideles' && $fiche->age() !== null)<tr><th>Âge</th><td>{{ $fiche->age() }} ans</td></tr>@endif
        @if($cle === 'cebs')<tr><th>Fidèles actifs</th><td>{{ \App\Models\Fidele::where('ceb_id', $fiche->id)->where('statut', 'actif')->count() }}</td></tr>@endif
        @if($cle === 'paroisses')<tr><th>Fidèles actifs</th><td>{{ \App\Models\Fidele::where('paroisse_id', $fiche->id)->where('statut', 'actif')->count() }}</td></tr>@endif
    </table>
</div>

@foreach($liees as $l)
    @php $colonnes = array_values(array_filter($l['def']['champs'], fn ($c) => ($c['liste'] ?? false) && $c['nom'] !== $l['cle'])); @endphp
    <div class="carte tableau">
        <h2>{{ $l['titre'] }} ({{ $l['fiches']->count() }})</h2>
        <table>
            <thead><tr>@foreach($colonnes as $c)<th>{{ $c['label'] }}</th>@endforeach<th></th></tr></thead>
            <tbody>
            @forelse($l['fiches'] as $f)
                <tr>
                    @foreach($colonnes as $c)<td>{{ champ_valeur($f, $c) }}</td>@endforeach
                    <td class="n"><a href="{{ route($l['ressource'].'.show', $f) }}" class="btn btn-leger btn-petit">Ouvrir</a></td>
                </tr>
            @empty
                <tr><td colspan="{{ count($colonnes) + 1 }}" class="vide">Aucun.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endforeach

<a href="{{ route("$cle.index") }}" class="btn btn-leger">← Retour à la liste</a>
@endsection
