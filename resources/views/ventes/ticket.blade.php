<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket {{ $vente->numero }}</title>
    <style>
        *{ box-sizing:border-box; margin:0; padding:0; }
        body{ font-family:'Courier New', monospace; font-size:12.5px; background:#eee; color:#000; }
        .ticket{ width:80mm; max-width:100%; margin:16px auto; background:#fff; padding:12px 10px; }
        .centre{ text-align:center; }
        .gras{ font-weight:bold; }
        .grand{ font-size:15px; }
        hr{ border:none; border-top:1px dashed #000; margin:8px 0; }
        table{ width:100%; border-collapse:collapse; }
        td{ padding:2px 0; vertical-align:top; }
        td.n{ text-align:right; white-space:nowrap; }
        .annule{ border:2px solid #000; padding:4px; margin:6px 0; text-align:center; font-weight:bold; }
        .boutons{ text-align:center; margin:14px; font-family:Arial, sans-serif; }
        .boutons a, .boutons button{ display:inline-block; margin:4px; padding:10px 16px; border-radius:7px; border:none; background:#1f4d3f; color:#fff; text-decoration:none; font-size:14px; cursor:pointer; }
        .boutons .ambre{ background:#e0a030; color:#0f2a22; font-weight:bold; }
        .succes{ background:#e1f1ea; color:#1f4d3f; padding:10px; text-align:center; font-family:Arial, sans-serif; }
        @media print{ body{ background:#fff; } .ticket{ margin:0; width:auto; } .boutons, .succes{ display:none; } }
    </style>
</head>
<body>
    @if(session('succes'))
        <div class="succes">{{ session('succes') }}</div>
    @endif

    <div class="ticket">
        <div class="centre gras grand">{{ $boutique->nom }}</div>
        @if($boutique->adresse)<div class="centre">{{ $boutique->adresse }}</div>@endif
        @if($boutique->telephone)<div class="centre">Tél : {{ $boutique->telephone }}</div>@endif
        <hr>
        <div>Ticket : {{ $vente->numero }}</div>
        <div>Date : {{ $vente->created_at->format('d/m/Y H:i') }}</div>
        <div>Vendeur : {{ $vente->user->name }}</div>
        @if($vente->client)<div>Client : {{ $vente->client->nom }}</div>@endif
        @if($vente->estAnnulee())<div class="annule">VENTE ANNULÉE</div>@endif
        <hr>
        <table>
            @foreach($vente->lignes as $l)
                <tr><td colspan="2">{{ $l->designation }}</td></tr>
                <tr><td>&nbsp;&nbsp;{{ $l->quantite }} × {{ fcfa($l->prix_unitaire) }}</td><td class="n">{{ fcfa($l->total) }}</td></tr>
            @endforeach
        </table>
        <hr>
        <table>
            <tr class="gras grand"><td>TOTAL</td><td class="n">{{ fcfa($vente->total) }}</td></tr>
            <tr><td>Payé ({{ \App\Models\Vente::MODES[$vente->mode_paiement] ?? $vente->mode_paiement }})</td><td class="n">{{ fcfa($vente->montant_paye) }}</td></tr>
            @if($vente->reste() > 0)
                <tr class="gras"><td>Reste à payer (crédit)</td><td class="n">{{ fcfa($vente->reste()) }}</td></tr>
            @endif
        </table>
        <hr>
        <div class="centre">{{ $boutique->pied_ticket ?: 'Merci de votre visite !' }}</div>
    </div>

    <div class="boutons">
        <button type="button" onclick="window.print()">Imprimer</button>
        <a class="ambre" href="{{ route('caisse.index') }}">Nouvelle vente</a>
        <a href="{{ route('ventes.show', $vente) }}">Détail</a>
    </div>
</body>
</html>
