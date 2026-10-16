@extends('layouts.app')
@section('title', 'Tableau de bord')

@section('content')
<div class="entete">
    <h1>Tableau de bord @if($secteurChoisie)— {{ $secteurChoisie->nom }}@endif</h1>
    @if($listeSecteurs->isNotEmpty())
        <form method="GET" class="actions">
            <select name="secteur" onchange="this.form.submit()" aria-label="Secteur">
                <option value="">Tout le vicariat</option>
                @foreach($listeSecteurs as $p)<option value="{{ $p->id }}" @selected($secteurChoisie?->id === $p->id)>{{ $p->nom }}</option>@endforeach
            </select>
        </form>
    @endif
</div>

@if(\App\Support\Ressources::peutLire(auth()->user(), 'fideles'))
<div class="grille">
    <div class="stat"><div class="lib">Fidèles (actifs)</div><div class="val">{{ number_format($resume['fideles'], 0, ',', ' ') }}</div><div class="det">{{ $resume['hommes'] }} hommes · {{ $resume['femmes'] }} femmes</div></div>
    <div class="stat"><div class="lib">Familles</div><div class="val">{{ number_format($resume['familles'], 0, ',', ' ') }}</div></div>
    <div class="stat"><div class="lib">CEB</div><div class="val">{{ $resume['cebs'] }}</div></div>
    <div class="stat"><div class="lib">Mouvements & pastorales</div><div class="val">{{ $resume['mouvements'] }}</div><div class="det">{{ number_format($resume['membres_mouvements'], 0, ',', ' ') }} membres déclarés</div></div>
    <div class="stat"><div class="lib">Sacrements en {{ today()->year }}</div><div class="val">{{ $resume['sacrements_annee'] }}</div>
        <div class="det">@foreach(\App\Support\Ressources::SACREMENTS as $k => $lib)@if(($parType[$k] ?? 0) > 0){{ $lib }} {{ $parType[$k] }} · @endif @endforeach</div></div>
</div>
@endif

@if($finances)
<h2>Finances</h2>
<div class="grille">
    <div class="stat"><div class="lib">Recettes du mois</div><div class="val positif">{{ fcfa($finances['mois']['recettes']) }}</div></div>
    <div class="stat"><div class="lib">Dépenses du mois</div><div class="val negatif">{{ fcfa($finances['mois']['depenses']) }}</div></div>
    <div class="stat"><div class="lib">Solde du mois</div><div class="val {{ $finances['mois']['solde'] >= 0 ? 'positif' : 'negatif' }}">{{ fcfa($finances['mois']['solde']) }}</div></div>
    <div class="stat"><div class="lib">Solde de l'année</div><div class="val {{ $finances['annee']['solde'] >= 0 ? 'positif' : 'negatif' }}">{{ fcfa($finances['annee']['solde']) }}</div><div class="det">recettes {{ fcfa($finances['annee']['recettes']) }}</div></div>
</div>
@endif

@if($comptes)
    <div class="entete"><h2 style="margin:0">Comptes</h2><a href="{{ route('rapports.index') }}" class="btn btn-leger btn-petit">Détail par CEB</a></div>
    <div class="grille">
        @if($comptes['central'])
            <div class="stat"><div class="lib">Caisse centrale du vicariat</div><div class="val positif">{{ fcfa($comptes['central']['caisse']) }}</div><div class="det">{{ fcfa($comptes['central']['recu']) }} reçus des secteurs</div></div>
        @endif
        <div class="stat"><div class="lib">{{ auth()->user()->estLimiteAUnSecteur() ? 'Compilé par le secteur' : 'Compilé dans le vicariat' }}</div><div class="val">{{ fcfa($comptes['total_compile']) }}</div></div>
        <div class="stat"><div class="lib">Déjà déposé au vicariat</div><div class="val positif">{{ fcfa($comptes['total_depose']) }}</div></div>
        <div class="stat"><div class="lib">Reste à déposer</div><div class="val {{ $comptes['total_reste'] > 0 ? 'negatif' : 'positif' }}">{{ fcfa($comptes['total_reste']) }}</div></div>
    </div>
@endif

<div class="deux-col">
    <div class="carte">
        <h2>Annonces en cours</h2>
        @forelse($annonces as $a)
            <p style="margin-bottom:10px"><a href="{{ route('annonces.show', $a) }}"><b>{{ $a->titre }}</b></a>
                @unless($a->secteur_id)<span class="badge badge-ambre">vicariat</span>@endunless<br>
                <span class="aide">{{ \Illuminate\Support\Str::limit($a->contenu, 120) }}</span></p>
        @empty
            <p class="vide">Aucune annonce.</p>
        @endforelse
    </div>
    <div class="carte">
        <h2>Prochaines activités</h2>
        @forelse($activites as $a)
            <p style="margin-bottom:10px"><b>{{ $a->jour('date_activite')->translatedFormat('D d/m') }}@if($a->heure) à {{ $a->heure }}@endif</b> —
                <a href="{{ route('activites.show', $a) }}">{{ $a->titre }}</a>
                @if($a->lieu)<span class="aide"> · {{ $a->lieu }}</span>@endif
                @unless($a->secteur_id)<span class="badge badge-ambre">vicariat</span>@endunless</p>
        @empty
            <p class="vide">Rien de prévu.</p>
        @endforelse
    </div>
</div>

@if($secteurs)
<div class="carte tableau">
    <h2>Les secteurs du vicariat</h2>
    <table>
        <thead><tr><th>Secteur</th><th class="n">Fidèles</th><th class="n">CEB</th><th class="n">Mouvements</th><th class="n">Sacrements {{ today()->year }}</th><th class="n">Recettes {{ today()->year }}</th><th class="n">Déposé au vicariat</th></tr></thead>
        <tbody>
        @forelse($secteurs as $l)
            <tr>
                <td><a href="{{ route('dashboard', ['secteur' => $l['secteur']->id]) }}"><b>{{ $l['secteur']->nom }}</b></a></td>
                <td class="n">{{ number_format($l['fideles'], 0, ',', ' ') }}</td><td class="n">{{ $l['cebs'] }}</td><td class="n">{{ $l['mouvements'] }}</td>
                <td class="n">{{ $l['sacrements'] }}</td><td class="n">{{ fcfa($l['recettes']) }}</td><td class="n">{{ fcfa($l['depose']) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="vide">Aucun secteur : <a href="{{ route('secteurs.create') }}">ajoutez la première</a>.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endif
@endsection
