@extends('layouts.app')
@section('title', 'Entrée de marchandise')

@section('content')
<h1>Entrée de marchandise</h1>

<form class="carte" method="POST" action="{{ route('stock.entree.store') }}" id="form-entree">
    @csrf
    <div class="ligne-champs">
        <div>
            <label for="fournisseur_id">Fournisseur</label>
            <select name="fournisseur_id" id="fournisseur_id">
                <option value="">— Non précisé —</option>
                @foreach($fournisseurs as $f)
                    <option value="{{ $f->id }}" @selected(old('fournisseur_id') == $f->id)>{{ $f->nom }}</option>
                @endforeach
            </select>
            <div class="aide"><a href="{{ route('fournisseurs.create') }}">+ Nouveau fournisseur</a></div>
        </div>
        <div style="flex:2">
            <label for="note">Note <span class="aide">(n° de facture, bon de livraison…)</span></label>
            <input type="text" name="note" id="note" value="{{ old('note') }}" maxlength="255">
        </div>
    </div>

    <div class="tableau" style="margin-top:16px">
        <table>
            <thead><tr><th>Produit</th><th style="width:120px">Quantité</th><th style="width:150px">Prix d'achat unitaire</th><th class="n">Sous-total</th><th></th></tr></thead>
            <tbody id="lignes"></tbody>
            <tfoot><tr><td colspan="3"><button type="button" class="btn btn-leger btn-petit" id="ajouter">+ Ajouter une ligne</button></td><td class="n"><b id="total">0 F</b></td><td></td></tr></tfoot>
        </table>
    </div>

    <label class="case" style="margin-top:14px"><input type="checkbox" name="maj_prix_achat" value="1" checked> Mettre à jour le prix d'achat des produits</label>
    <label class="case"><input type="checkbox" name="enregistrer_depense" value="1"> Enregistrer aussi le paiement comme dépense « Achat marchandise » (suivi de la trésorerie, sans réduire le bénéfice)</label>

    <button class="btn btn-ambre" style="margin-top:16px">Enregistrer l'entrée</button>
</form>

<template id="modele-ligne">
    <tr>
        <td>
            <select data-champ="produit_id" required>
                <option value="">— Choisir —</option>
                @foreach($produits as $p)
                    <option value="{{ $p->id }}" data-prix="{{ $p->prix_achat }}">{{ $p->nom }} (stock {{ $p->stock }})</option>
                @endforeach
            </select>
        </td>
        <td><input type="number" data-champ="quantite" min="1" value="1" required></td>
        <td><input type="number" data-champ="prix_achat" min="0" value="0" required></td>
        <td class="n" data-sous-total>0 F</td>
        <td><button type="button" class="btn btn-leger btn-petit" data-retirer>✕</button></td>
    </tr>
</template>
@endsection

@push('scripts')
<script>
(function () {
    const fmt = n => new Intl.NumberFormat('fr-FR').format(n).replace(/ | /g, ' ') + ' F';
    const tbody = document.getElementById('lignes');
    let index = 0;

    function recalculer() {
        let total = 0;
        tbody.querySelectorAll('tr').forEach(tr => {
            const st = (parseInt(tr.querySelector('[data-champ=quantite]').value) || 0) * (parseInt(tr.querySelector('[data-champ=prix_achat]').value) || 0);
            tr.querySelector('[data-sous-total]').textContent = fmt(st);
            total += st;
        });
        document.getElementById('total').textContent = fmt(total);
    }

    function ajouterLigne(produitId) {
        const tr = document.getElementById('modele-ligne').content.firstElementChild.cloneNode(true);
        const i = index++;
        tr.querySelectorAll('[data-champ]').forEach(el => el.name = 'lignes[' + i + '][' + el.dataset.champ + ']');
        const select = tr.querySelector('select');
        select.addEventListener('change', () => {
            const opt = select.selectedOptions[0];
            tr.querySelector('[data-champ=prix_achat]').value = opt?.dataset.prix || 0;
            recalculer();
        });
        tr.addEventListener('input', recalculer);
        tr.querySelector('[data-retirer]').onclick = () => { tr.remove(); recalculer(); };
        tbody.appendChild(tr);
        if (produitId) { select.value = produitId; select.dispatchEvent(new Event('change')); }
    }

    document.getElementById('ajouter').onclick = () => ajouterLigne();
    ajouterLigne(@json($produitChoisi));
})();
</script>
@endpush
