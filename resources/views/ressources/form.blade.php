@extends('layouts.app')
@section('title', $fiche->exists ? 'Modifier' : 'Nouveau')

@section('content')
<h1>{{ $fiche->exists ? 'Modifier « '.$fiche->intitule().' »' : ucfirst($def['singulier']).' — nouveau' }}</h1>

<form class="carte formulaire" method="POST" action="{{ $fiche->exists ? route("$cle.update", $fiche) : route("$cle.store") }}">
    @csrf
    @if($fiche->exists) @method('PUT') @endif

    @foreach($def['champs'] as $champ)
        @php
            $nom = $champ['nom'];
            $valeur = old($nom, $prefill[$nom] ?? null);
            if ($valeur === null) {
                if ($champ['type'] === 'ref') { $valeur = $fiche->{\App\Support\Ressources::relation($champ)}?->uuid; }
                elseif ($champ['type'] === 'date') { $valeur = $fiche->$nom ? substr($fiche->$nom, 0, 10) : null; }
                else { $valeur = $fiche->$nom; }
            }
            $requis = $champ['requis'] ?? false;
        @endphp
        @if(! ($champ['nom'] === 'secteur_uuid' && auth()->user()->estLimiteAUnSecteur()))
        <label for="{{ $nom }}">{{ $champ['label'] }}@if($requis) *@endif</label>
        @switch($champ['type'])
            @case('long')
                <textarea id="{{ $nom }}" name="{{ $nom }}" rows="4" maxlength="{{ $champ['max'] ?? 2000 }}" @required($requis)>{{ $valeur }}</textarea>
                @break
            @case('choix')
                <select id="{{ $nom }}" name="{{ $nom }}" @required($requis)>
                    @unless($requis)<option value="">—</option>@endunless
                    @if($requis && $valeur === null)<option value="" disabled selected>Choisir…</option>@endif
                    @foreach($champ['options'] as $k => $lib)<option value="{{ $k }}" @selected((string) $valeur === (string) $k)>{{ $lib }}</option>@endforeach
                </select>
                @break
            @case('ref')
                @php $liste = $options[$nom] ?? collect(); @endphp
                @if($liste->count() > 25)
                    <input type="search" placeholder="Rechercher dans la liste…" class="pas-imprimer" style="margin-bottom:6px"
                           oninput="const t=this.value.toLowerCase(); for (const o of document.getElementById('{{ $nom }}').options) o.hidden = o.value && !o.text.toLowerCase().includes(t)">
                @endif
                <select id="{{ $nom }}" name="{{ $nom }}" @required($requis)>
                    @unless($requis)<option value="">{{ $nom === 'secteur_uuid' ? 'Tout le vicariat' : '—' }}</option>@endunless
                    @if($requis && $valeur === null)<option value="" disabled selected>Choisir…</option>@endif
                    @foreach($liste as $o)<option value="{{ $o['uuid'] }}" @selected($valeur === $o['uuid'])>{{ $o['texte'] }}</option>@endforeach
                </select>
                @break
            @case('date')
                <input type="date" id="{{ $nom }}" name="{{ $nom }}" value="{{ $valeur }}" @required($requis)>
                @break
            @case('annee')
                <input type="text" id="{{ $nom }}" name="{{ $nom }}" value="{{ $valeur }}" pattern="\d{4}-\d{4}" placeholder="2026-2027" maxlength="9" @required($requis)>
                @break
            @case('heure')
                <input type="time" id="{{ $nom }}" name="{{ $nom }}" value="{{ $valeur }}">
                @break
            @case('entier')
            @case('montant')
                <input type="number" id="{{ $nom }}" name="{{ $nom }}" value="{{ $valeur }}" min="{{ $champ['type'] === 'montant' ? 1 : 0 }}" step="1" @required($requis)>
                @break
            @default
                <input type="{{ $champ['type'] === 'tel' ? 'tel' : 'text' }}" id="{{ $nom }}" name="{{ $nom }}" value="{{ $valeur }}" maxlength="{{ $champ['max'] ?? 255 }}" @required($requis)>
        @endswitch
        @isset($champ['aide'])<div class="aide">{{ $champ['aide'] }}</div>@endisset
        @endif
    @endforeach

    <div class="actions" style="margin-top:16px">
        <button class="btn btn-ambre">Enregistrer</button>
        <a href="{{ $fiche->exists ? route("$cle.show", $fiche) : route("$cle.index") }}" class="btn btn-leger">Annuler</a>
    </div>
</form>
@endsection
