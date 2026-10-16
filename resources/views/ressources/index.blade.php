@extends('layouts.app')
@section('title', $def['libelle'])

@section('content')
<div class="entete">
    <div>
        <h1 style="margin:0">{{ $def['libelle'] }}</h1>
        @isset($def['sous_titre'])<div class="aide">{{ $def['sous_titre'] }}</div>@endisset
    </div>
    @if($peutEcrire)<a href="{{ route("$cle.create") }}" class="btn btn-ambre">+ Ajouter {{ $def['article'] }} {{ $def['singulier'] }}</a>@endif
</div>

<form class="filtres" method="GET">
    <div style="flex:1; min-width:180px"><label for="q">Recherche</label><input type="search" id="q" name="q" value="{{ request('q') }}"></div>
    @foreach($def['champs'] as $champ)
        @if($champ['type'] === 'choix' && ($champ['liste'] ?? false))
            <div><label for="f-{{ $champ['nom'] }}">{{ $champ['label'] }}</label>
                <select id="f-{{ $champ['nom'] }}" name="{{ $champ['nom'] }}">
                    <option value="">Tous</option>
                    @foreach($champ['options'] as $k => $lib)<option value="{{ $k }}" @selected(request($champ['nom']) === (string) $k)>{{ $lib }}</option>@endforeach
                </select></div>
        @elseif($champ['type'] === 'ref' && isset($options[$champ['nom']]) && count($options[$champ['nom']]) > 1)
            <div><label for="f-{{ $champ['nom'] }}">{{ $champ['label'] }}</label>
                <select id="f-{{ $champ['nom'] }}" name="{{ $champ['nom'] }}">
                    <option value="">Toutes</option>
                    @foreach($options[$champ['nom']] as $o)<option value="{{ $o['uuid'] }}" @selected(request($champ['nom']) === $o['uuid'])>{{ $o['texte'] }}</option>@endforeach
                </select></div>
        @endif
    @endforeach
    @if($dateChamp)
        <div><label for="du">Du</label><input type="date" id="du" name="du" value="{{ request('du') }}"></div>
        <div><label for="au">Au</label><input type="date" id="au" name="au" value="{{ request('au') }}"></div>
    @endif
    <button class="btn">Filtrer</button>
    @if(request()->query())<a href="{{ route("$cle.index") }}" class="btn btn-leger">Effacer</a>@endif
</form>

<div class="grille">
    <div class="stat"><div class="lib">Nombre</div><div class="val">{{ number_format($total, 0, ',', ' ') }}</div></div>
    @if($resume && isset($resume['recettes']))
        <div class="stat"><div class="lib">Recettes</div><div class="val positif">{{ fcfa($resume['recettes']) }}</div></div>
        <div class="stat"><div class="lib">Dépenses</div><div class="val negatif">{{ fcfa($resume['depenses']) }}</div></div>
        <div class="stat"><div class="lib">Solde</div><div class="val {{ $resume['recettes'] >= $resume['depenses'] ? 'positif' : 'negatif' }}">{{ fcfa($resume['recettes'] - $resume['depenses']) }}</div></div>
    @endif
    @if($resume && isset($resume['membres']))
        <div class="stat"><div class="lib">Membres déclarés</div><div class="val">{{ number_format($resume['membres'], 0, ',', ' ') }}</div></div>
    @endif
</div>

@php $colonnes = array_values(array_filter($def['champs'], fn ($c) => $c['liste'] ?? false)); @endphp
<div class="carte tableau">
    <table>
        <thead><tr>@foreach($colonnes as $c)<th @class(['n' => $c['type'] === 'montant'])>{{ $c['label'] }}</th>@endforeach<th></th></tr></thead>
        <tbody>
        @forelse($fiches as $f)
            <tr>
                @foreach($colonnes as $c)
                    <td @class(['n' => $c['type'] === 'montant'])>
                        @if($loop->first)<a href="{{ route("$cle.show", $f) }}"><b>{{ champ_valeur($f, $c) }}</b></a>
                        @elseif($c['nom'] === 'sens')<span class="badge {{ $f->sens === 'recette' ? 'badge-vert' : 'badge-rouge' }}">{{ champ_valeur($f, $c) }}</span>
                        @elseif($c['nom'] === 'statut' && $f->statut !== 'actif')<span class="badge badge-gris">{{ champ_valeur($f, $c) }}</span>
                        @else{{ champ_valeur($f, $c) }}@endif
                    </td>
                @endforeach
                <td class="n"><a href="{{ route("$cle.show", $f) }}" class="btn btn-leger btn-petit">Ouvrir</a></td>
            </tr>
        @empty
            <tr><td colspan="{{ count($colonnes) + 1 }}" class="vide">Rien à afficher.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $fiches->links() }}
@endsection
