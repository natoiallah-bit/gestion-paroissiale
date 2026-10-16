@extends('layouts.app')
@section('title', $produit->nom)

@section('content')
<div class="entete">
    <h1>{{ $produit->nom }} @unless($produit->actif)<span class="badge badge-gris">Inactif</span>@endunless</h1>
    <div class="actions">
        <a href="{{ route('stock.entree', ['produit' => $produit->id]) }}" class="btn btn-ambre">Entrée de stock</a>
        <a href="{{ route('produits.edit', $produit) }}" class="btn btn-leger">Modifier</a>
        <form method="POST" action="{{ route('produits.destroy', $produit) }}" onsubmit="return confirm('Supprimer ce produit ? (s\'il a déjà été vendu, il sera seulement désactivé)')">
            @csrf @method('DELETE')
            <button class="btn btn-danger">Supprimer</button>
        </form>
    </div>
</div>

<div class="grille">
    <div class="stat"><div class="lib">Stock</div><div class="val {{ $produit->estEnAlerte() ? 'negatif' : '' }}">{{ $produit->stock }} {{ $produit->unite }}</div><div class="det">alerte à {{ $produit->seuil_alerte }}</div></div>
    <div class="stat"><div class="lib">Prix de vente</div><div class="val">{{ fcfa($produit->prix_vente) }}</div></div>
    <div class="stat"><div class="lib">Prix d'achat</div><div class="val">{{ fcfa($produit->prix_achat) }}</div><div class="det">marge {{ fcfa($produit->prix_vente - $produit->prix_achat) }} / {{ $produit->unite }}</div></div>
    <div class="stat"><div class="lib">Catégorie · code</div><div class="val" style="font-size:16px">{{ $produit->categorie?->nom ?? '—' }}</div><div class="det">{{ $produit->code ?: 'sans code' }}</div></div>
</div>

<form class="carte" method="POST" action="{{ route('stock.ajuster', $produit) }}">
    @csrf
    <h2>Ajuster le stock (inventaire, casse, perte…)</h2>
    <div class="ligne-champs">
        <div><label for="stock_reel">Stock réellement compté</label><input type="number" id="stock_reel" name="stock_reel" min="0" value="{{ $produit->stock }}" required></div>
        <div style="flex:2"><label for="note">Raison</label><input type="text" id="note" name="note" required maxlength="255" placeholder="Inventaire du mois, article cassé…"></div>
    </div>
    <button class="btn" style="margin-top:12px">Enregistrer l'ajustement</button>
</form>

<div class="carte tableau">
    <h2>Historique du stock</h2>
    <table>
        <thead><tr><th>Date</th><th>Type</th><th class="n">Quantité</th><th class="n">Stock après</th><th>Détail</th><th>Par</th></tr></thead>
        <tbody>
        @forelse($mouvements as $m)
            <tr>
                <td>{{ $m->created_at->format('d/m/Y H:i') }}</td>
                <td>{{ $m->libelleType() }}</td>
                <td class="n {{ $m->quantite < 0 ? 'negatif' : 'positif' }}">{{ $m->quantite > 0 ? '+' : '' }}{{ $m->quantite }}</td>
                <td class="n">{{ $m->stock_apres }}</td>
                <td>
                    @if($m->vente)<a href="{{ route('ventes.show', $m->vente) }}">{{ $m->vente->numero }}</a>@endif
                    @if($m->fournisseur){{ $m->fournisseur->nom }}@endif
                    @if($m->prix_achat_unitaire !== null && $m->type === 'entree') · {{ fcfa($m->prix_achat_unitaire) }}/u @endif
                    {{ $m->note }}
                </td>
                <td>{{ $m->user->name }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="vide">Aucun mouvement.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $mouvements->links() }}
</div>
@endsection
