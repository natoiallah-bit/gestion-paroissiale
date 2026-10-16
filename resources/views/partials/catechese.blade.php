{{-- Catéchèse d'une année pastorale (voir Statistiques::catechese) ; $detail = tableaux par niveau et par secteur --}}
<div class="grille">
    <div class="stat"><div class="lib">Groupes ({{ $catechese['annee'] }})</div><div class="val">{{ $catechese['groupes'] }}</div></div>
    <div class="stat"><div class="lib">Catéchistes actifs</div><div class="val">{{ $catechese['catechistes'] }}</div></div>
    <div class="stat"><div class="lib">Catéchumènes inscrits</div><div class="val">{{ number_format($catechese['inscrits'], 0, ',', ' ') }}</div><div class="det">{{ $catechese['admis'] }} admis</div></div>
    <div class="stat"><div class="lib">Séances</div><div class="val">{{ $catechese['seances'] }}</div><div class="det">@if($catechese['presence'] !== null)présence moyenne {{ $catechese['presence'] }} %@else aucune présence saisie @endif</div></div>
</div>

@if($detail ?? false)
<div class="carte tableau">
    <h2>Catéchèse {{ $catechese['annee'] }} — par niveau</h2>
    <table>
        <thead><tr><th>Niveau</th><th class="n">Groupes</th><th class="n">Inscrits</th>@foreach(\App\Support\Ressources::STATUTS_INSCRIPTION as $lib)<th class="n">{{ $lib }}</th>@endforeach</tr></thead>
        <tbody>
        @foreach($catechese['par_niveau'] as $n)
            <tr><td>{{ $n['libelle'] }}</td><td class="n">{{ $n['groupes'] }}</td><td class="n"><b>{{ $n['total'] }}</b></td>@foreach(array_keys(\App\Support\Ressources::STATUTS_INSCRIPTION) as $k)<td class="n">{{ $n[$k] }}</td>@endforeach</tr>
        @endforeach
        </tbody>
    </table>
</div>
@if($catechese['par_secteur'])
<div class="carte tableau">
    <h2>Catéchèse {{ $catechese['annee'] }} — par secteur</h2>
    <table>
        <thead><tr><th>Secteur</th><th class="n">Groupes</th><th class="n">Catéchistes actifs</th><th class="n">Inscrits</th><th class="n">Admis</th></tr></thead>
        <tbody>
        @foreach($catechese['par_secteur'] as $l)
            <tr><td><b>{{ $l['secteur']->nom }}</b></td><td class="n">{{ $l['groupes'] }}</td><td class="n">{{ $l['catechistes'] }}</td><td class="n">{{ $l['inscrits'] }}</td><td class="n">{{ $l['admis'] }}</td></tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif
@endif
