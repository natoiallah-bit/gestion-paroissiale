{{-- Compilation des comptes : CEB → secteur → vicariat (voir Statistiques::comptes) --}}
@php $limite = auth()->user()->estLimiteAUnSecteur(); @endphp
<div class="grille">
    @if($comptes['central'])
        <div class="stat"><div class="lib">Caisse centrale du vicariat</div><div class="val positif">{{ fcfa($comptes['central']['caisse']) }}</div>
            <div class="det">{{ fcfa($comptes['central']['recu']) }} reçus des secteurs @if($comptes['central']['propres']['solde'] != 0)· opérations propres {{ fcfa($comptes['central']['propres']['solde']) }}@endif</div></div>
    @endif
    <div class="stat"><div class="lib">{{ $limite ? 'Compilé par le secteur' : 'Compilé dans tout le vicariat' }}</div><div class="val">{{ fcfa($comptes['total_compile']) }}</div><div class="det">comptes des CEB + secteur</div></div>
    <div class="stat"><div class="lib">Déjà déposé au vicariat</div><div class="val positif">{{ fcfa($comptes['total_depose']) }}</div></div>
    <div class="stat"><div class="lib">Reste à déposer</div><div class="val {{ $comptes['total_reste'] > 0 ? 'negatif' : 'positif' }}">{{ fcfa($comptes['total_reste']) }}</div></div>
</div>

@foreach($comptes['secteurs'] as $l)
    <div class="carte tableau">
        <h2>Secteur {{ $l['secteur']->nom }}
            <span class="badge {{ $l['reste'] > 0 ? 'badge-ambre' : 'badge-vert' }}">reste à déposer : {{ fcfa($l['reste']) }}</span></h2>
        <table>
            <thead><tr><th>Compte</th><th class="n">Dépôts / recettes</th><th class="n">Retraits / dépenses</th><th class="n">Solde</th></tr></thead>
            <tbody>
            @foreach($l['cebs'] as $c)
                <tr><td><a href="{{ route('cebs.show', $c['ceb']) }}">{{ $c['ceb']->nom }}</a></td>
                    <td class="n">{{ fcfa($c['recettes']) }}</td><td class="n">{{ fcfa($c['depenses']) }}</td><td class="n"><b>{{ fcfa($c['solde']) }}</b></td></tr>
            @endforeach
            <tr><td>Opérations propres du secteur</td><td class="n">{{ fcfa($l['propres']['recettes']) }}</td><td class="n">{{ fcfa($l['propres']['depenses']) }}</td><td class="n"><b>{{ fcfa($l['propres']['solde']) }}</b></td></tr>
            <tr><td><b>Compilation du secteur</b></td><td class="n">{{ fcfa($l['compile']['recettes']) }}</td><td class="n">{{ fcfa($l['compile']['depenses']) }}</td><td class="n"><b>{{ fcfa($l['compile']['solde']) }}</b></td></tr>
            <tr><td>Déposé au vicariat ({{ $l['nb_depots'] }} dépôt{{ $l['nb_depots'] > 1 ? 's' : '' }})</td><td></td><td class="n">{{ fcfa($l['depose']) }}</td><td class="n"><b>{{ fcfa($l['reste']) }}</b> <span class="aide">à déposer</span></td></tr>
            </tbody>
        </table>
    </div>
@endforeach
