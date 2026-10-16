@extends('layouts.app')
@section('title', 'Rapports')

@section('content')
<div class="entete pas-imprimer"><h1>Rapports</h1><button class="btn btn-leger" onclick="window.print()">Imprimer</button></div>

<form class="filtres pas-imprimer" method="GET">
    @if($paroisses->isNotEmpty())
        <div><label for="paroisse">Paroisse</label>
            <select id="paroisse" name="paroisse"><option value="">Tout le vicariat</option>
                @foreach($paroisses as $p)<option value="{{ $p->id }}" @selected((int) $paroisseId === $p->id)>{{ $p->nom }}</option>@endforeach
            </select></div>
    @endif
    <div><label for="du">Finances du</label><input type="date" id="du" name="du" value="{{ $du->toDateString() }}"></div>
    <div><label for="au">au</label><input type="date" id="au" name="au" value="{{ $au->toDateString() }}"></div>
    <div><label for="annee">Sacrements de l'année</label><input type="number" id="annee" name="annee" value="{{ $annee }}" min="1900" max="2200"></div>
    <button class="btn">Afficher</button>
</form>

@if($effectifs)
<h2>Effectifs</h2>
<div class="grille">
    <div class="stat"><div class="lib">Fidèles actifs</div><div class="val">{{ number_format($effectifs['fideles'], 0, ',', ' ') }}</div><div class="det">{{ $effectifs['hommes'] }} hommes · {{ $effectifs['femmes'] }} femmes</div></div>
    <div class="stat"><div class="lib">Familles</div><div class="val">{{ $effectifs['familles'] }}</div></div>
    <div class="stat"><div class="lib">CEB</div><div class="val">{{ $effectifs['cebs'] }}</div></div>
    <div class="stat"><div class="lib">Mouvements & services</div><div class="val">{{ $effectifs['mouvements'] }}</div><div class="det">{{ number_format($effectifs['membres_mouvements'], 0, ',', ' ') }} membres</div></div>
</div>
@endif

@if($sacrements)
<div class="carte tableau">
    <h2>Sacrements célébrés en {{ $annee }}</h2>
    <table>
        <thead><tr><th>Sacrement</th><th class="n">Nombre</th></tr></thead>
        <tbody>
        @foreach($sacrements as $s)<tr><td>{{ $s['libelle'] }}</td><td class="n">{{ $s['n'] }}</td></tr>@endforeach
        <tr><td><b>Total</b></td><td class="n"><b>{{ collect($sacrements)->sum('n') }}</b></td></tr>
        </tbody>
    </table>
</div>
@endif

@if($finances)
<div class="grille">
    <div class="stat"><div class="lib">Recettes</div><div class="val positif">{{ fcfa($finances['recettes']) }}</div></div>
    <div class="stat"><div class="lib">Dépenses</div><div class="val negatif">{{ fcfa($finances['depenses']) }}</div></div>
    <div class="stat"><div class="lib">Solde</div><div class="val {{ $finances['solde'] >= 0 ? 'positif' : 'negatif' }}">{{ fcfa($finances['solde']) }}</div></div>
</div>
<div class="carte tableau">
    <h2>Finances du {{ $du->format('d/m/Y') }} au {{ $au->format('d/m/Y') }}</h2>
    <table>
        <thead><tr><th>Catégorie</th><th>Sens</th><th class="n">Opérations</th><th class="n">Total</th></tr></thead>
        <tbody>
        @forelse($finances['par_categorie'] as $l)
            <tr><td>{{ $l['categorie'] }}</td>
                <td><span class="badge {{ $l['sens'] === 'recette' ? 'badge-vert' : 'badge-rouge' }}">{{ $l['sens'] === 'recette' ? 'Recette' : 'Dépense' }}</span></td>
                <td class="n">{{ $l['n'] }}</td><td class="n">{{ fcfa($l['total']) }}</td></tr>
        @empty
            <tr><td colspan="4" class="vide">Aucune opération sur la période.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endif

@if($parParoisse)
<div class="carte tableau">
    <h2>Par paroisse</h2>
    <table>
        <thead><tr><th>Paroisse</th><th class="n">Fidèles</th><th class="n">CEB</th><th class="n">Mouvements</th><th class="n">Sacrements {{ today()->year }}</th><th class="n">Recettes {{ today()->year }}</th></tr></thead>
        <tbody>
        @foreach($parParoisse as $l)
            <tr><td><b>{{ $l['paroisse']->nom }}</b></td><td class="n">{{ number_format($l['fideles'], 0, ',', ' ') }}</td><td class="n">{{ $l['cebs'] }}</td><td class="n">{{ $l['mouvements'] }}</td><td class="n">{{ $l['sacrements'] }}</td><td class="n">{{ fcfa($l['recettes']) }}</td></tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
