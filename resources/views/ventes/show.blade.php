@extends('layouts.app')
@section('title', 'Vente '.$vente->numero)

@section('content')
<div class="entete">
    <h1>Vente {{ $vente->numero }}
        @if($vente->estAnnulee())<span class="badge badge-gris">Annulée</span>@endif
    </h1>
    <div class="actions">
        <a href="{{ route('ventes.ticket', $vente) }}" class="btn btn-leger">Ticket</a>
        <a href="{{ route('ventes.index') }}" class="btn btn-leger">Retour</a>
    </div>
</div>

<div class="grille">
    <div class="stat"><div class="lib">Date</div><div class="val" style="font-size:17px">{{ $vente->created_at->format('d/m/Y H:i') }}</div><div class="det">par {{ $vente->user->name }}</div></div>
    <div class="stat"><div class="lib">Client</div><div class="val" style="font-size:17px">
        @if($vente->client)<a href="{{ route('clients.show', $vente->client) }}">{{ $vente->client->nom }}</a>@else Client de passage @endif
    </div></div>
    <div class="stat"><div class="lib">Total</div><div class="val">{{ fcfa($vente->total) }}</div></div>
    <div class="stat"><div class="lib">Payé ({{ \App\Models\Vente::MODES[$vente->mode_paiement] ?? $vente->mode_paiement }})</div><div class="val">{{ fcfa($vente->montant_paye) }}</div>
        @if($vente->reste() > 0)<div class="det negatif">Crédit : {{ fcfa($vente->reste()) }}</div>@endif
    </div>
</div>

<div class="carte tableau">
    <table>
        <thead><tr><th>Article</th><th class="n">Qté</th><th class="n">Prix</th><th class="n">Total</th>
            @if(auth()->user()->estGerant())<th class="n">Marge</th>@endif
        </tr></thead>
        <tbody>
        @foreach($vente->lignes as $l)
            <tr>
                <td>{{ $l->designation }}</td>
                <td class="n">{{ $l->quantite }}</td>
                <td class="n">{{ fcfa($l->prix_unitaire) }}</td>
                <td class="n">{{ fcfa($l->total) }}</td>
                @if(auth()->user()->estGerant())<td class="n">{{ fcfa($l->total - $l->quantite * $l->prix_achat_unitaire) }}</td>@endif
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

@if($vente->estAnnulee())
    <div class="carte">
        Annulée le {{ $vente->annulee_le->format('d/m/Y H:i') }} par {{ $vente->annuleePar?->name }}.
        Motif : {{ $vente->motif_annulation }}
    </div>
@elseif(auth()->user()->estGerant())
    <form class="carte formulaire" method="POST" action="{{ route('ventes.annuler', $vente) }}"
          onsubmit="return confirm('Annuler cette vente ? Les articles seront remis en stock.')">
        @csrf
        <h2>Annuler la vente</h2>
        <p class="aide">Les articles retournent en stock et le crédit éventuel du client est effacé.</p>
        <label for="motif">Motif</label>
        <input type="text" id="motif" name="motif" required maxlength="255" placeholder="Erreur de saisie, retour client…">
        <button class="btn btn-danger" style="margin-top:12px">Annuler la vente</button>
    </form>
@endif
@endsection
