@extends('layouts.app')
@section('title', $client->nom)

@section('content')
<div class="entete">
    <h1>{{ $client->nom }}</h1>
    <div class="actions">
        <a href="{{ route('clients.edit', $client) }}" class="btn btn-leger">Modifier</a>
        @if(auth()->user()->estGerant() && $historique->isEmpty())
            <form method="POST" action="{{ route('clients.destroy', $client) }}" onsubmit="return confirm('Supprimer ce client ?')">
                @csrf @method('DELETE')
                <button class="btn btn-danger">Supprimer</button>
            </form>
        @endif
    </div>
</div>

<div class="deux-col">
    <div>
        <div class="grille">
            <div class="stat"><div class="lib">Doit actuellement</div><div class="val {{ $dette > 0 ? 'negatif' : 'positif' }}">{{ fcfa($dette) }}</div></div>
        </div>
        <div class="carte">
            <div>📞 {{ $client->telephone ?? 'Pas de téléphone' }}</div>
            @if($client->adresse)<div style="margin-top:6px">📍 {{ $client->adresse }}</div>@endif
        </div>
    </div>

    @if($dette > 0)
        <form class="carte" method="POST" action="{{ route('clients.rembourser', $client) }}">
            @csrf
            <h2>Enregistrer un remboursement</h2>
            <div class="ligne-champs">
                <div><label for="montant">Montant (F)</label><input type="number" id="montant" name="montant" min="1" max="{{ $dette }}" value="{{ old('montant', $dette) }}" required></div>
                <div>
                    <label for="mode_paiement">Mode</label>
                    <select id="mode_paiement" name="mode_paiement">
                        @foreach(\App\Models\Vente::MODES as $cle => $lib)<option value="{{ $cle }}">{{ $lib }}</option>@endforeach
                    </select>
                </div>
            </div>
            <label for="note">Note</label>
            <input type="text" id="note" name="note" maxlength="255">
            <button class="btn btn-ambre" style="margin-top:12px">Enregistrer</button>
        </form>
    @endif
</div>

<div class="carte tableau">
    <h2>Historique</h2>
    <table>
        <thead><tr><th>Date</th><th>Opération</th><th class="n">Montant</th><th class="n">Crédit / remboursé</th><th>Par</th></tr></thead>
        <tbody>
        @forelse($historique as $h)
            @php $o = $h['objet']; @endphp
            @if($h['type'] === 'vente')
                <tr @class(['barre' => $o->estAnnulee()])>
                    <td>{{ $o->created_at->format('d/m/Y H:i') }}</td>
                    <td>Achat <a href="{{ route('ventes.show', $o) }}">{{ $o->numero }}</a> @if($o->estAnnulee())<span class="badge badge-gris">annulé</span>@endif</td>
                    <td class="n">{{ fcfa($o->total) }}</td>
                    <td class="n {{ $o->reste() > 0 ? 'negatif' : '' }}">{{ $o->reste() > 0 ? '+ '.fcfa($o->reste()) : '—' }}</td>
                    <td>{{ $o->user->name }}</td>
                </tr>
            @else
                <tr>
                    <td>{{ $o->created_at->format('d/m/Y H:i') }}</td>
                    <td>Remboursement ({{ \App\Models\Vente::MODES[$o->mode_paiement] ?? $o->mode_paiement }}) {{ $o->note }}</td>
                    <td class="n"></td>
                    <td class="n positif">− {{ fcfa($o->montant) }}</td>
                    <td>{{ $o->user->name }}</td>
                </tr>
            @endif
        @empty
            <tr><td colspan="5" class="vide">Aucune opération.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
