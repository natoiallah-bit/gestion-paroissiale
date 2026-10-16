@extends('layouts.app')
@section('title', 'Caisse')

@section('styles')
    .caisse{ display:grid; grid-template-columns:minmax(0,1fr) 380px; gap:18px; align-items:start; }
    .catalogue{ display:grid; grid-template-columns:repeat(auto-fill, minmax(150px,1fr)); gap:10px; margin-top:12px; max-height:calc(100vh - 190px); overflow-y:auto; padding:2px; }
    .tuile{ background:#fff; border:1px solid var(--ligne); border-radius:9px; padding:10px; text-align:left; cursor:pointer; font-family:inherit; font-size:13px; }
    .tuile:hover{ border-color:var(--ambre-500); }
    .tuile .nom{ font-weight:600; min-height:34px; }
    .tuile .prix{ font-weight:700; color:var(--vert-800); margin-top:6px; }
    .tuile .stk{ font-size:11.5px; color:var(--doux); }
    .tuile[disabled]{ opacity:.45; cursor:not-allowed; }
    .panier{ position:sticky; top:16px; }
    .panier table td{ padding:7px 6px; font-size:13px; }
    .qte{ display:flex; align-items:center; gap:4px; }
    .qte button{ width:26px; height:26px; border:1px solid var(--ligne); background:#fff; border-radius:5px; cursor:pointer; font-weight:700; }
    .qte input{ width:52px; text-align:center; padding:4px; }
    .total-gros{ font-size:28px; font-weight:700; text-align:right; margin:12px 0 4px; font-variant-numeric:tabular-nums; }
    .resume{ display:flex; justify-content:space-between; font-size:13.5px; margin-top:6px; }
    .valider{ width:100%; padding:13px; font-size:16px; margin-top:14px; }
    .barre-panier{ display:none; }
    @media (max-width:1000px){
        .caisse{ grid-template-columns:1fr; } .catalogue{ max-height:none; } .panier{ position:static; }
        .barre-panier{ display:flex; position:fixed; left:0; right:0; bottom:0; z-index:5; justify-content:space-between; align-items:center;
            background:var(--vert-950); color:#fff; padding:12px 16px; font-weight:700; text-decoration:none; }
        main{ padding-bottom:80px; }
    }
@endsection

@section('content')
<div class="caisse">
    <section>
        <div class="entete" style="margin-bottom:0">
            <h1>Caisse</h1>
        </div>
        <input type="search" id="recherche" placeholder="Rechercher un produit ou scanner un code-barres puis Entrée…" autofocus autocomplete="off">
        <div class="catalogue" id="catalogue">
            @forelse($produits as $p)
                <button type="button" class="tuile" data-id="{{ $p->id }}"
                        data-cherche="{{ mb_strtolower($p->nom.' '.$p->code.' '.$p->categorie?->nom) }}" data-code="{{ $p->code }}"
                        @disabled($p->stock <= 0)>
                    <div class="nom">{{ $p->nom }}</div>
                    <div class="prix">{{ fcfa($p->prix_vente) }}</div>
                    <div class="stk">Stock : {{ $p->stock }} {{ $p->unite }}</div>
                </button>
            @empty
                <div class="vide">Aucun produit. Le gérant doit d'abord en ajouter dans « Produits ».</div>
            @endforelse
        </div>
    </section>

    <form class="carte panier" method="POST" action="{{ route('caisse.store') }}" id="form-vente">
        @csrf
        <h2>Panier</h2>
        <div class="tableau">
            <table>
                <tbody id="lignes"></tbody>
            </table>
        </div>
        <div id="panier-vide" class="vide">Cliquez sur un produit pour l'ajouter.</div>

        <div class="total-gros" id="total">0 F</div>

        <label for="client_id">Client <span class="aide">(obligatoire pour une vente à crédit)</span></label>
        <select name="client_id" id="client_id">
            <option value="">— Client de passage —</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}" @selected(old('client_id') == $c->id)>{{ $c->nom }}{{ $c->telephone ? ' · '.$c->telephone : '' }}</option>
            @endforeach
        </select>
        <div class="aide"><a href="{{ route('clients.create') }}" target="_blank">+ Nouveau client</a> (puis rechargez la page)</div>

        <div class="ligne-champs">
            <div>
                <label for="mode_paiement">Paiement</label>
                <select name="mode_paiement" id="mode_paiement">
                    @foreach(\App\Models\Vente::MODES as $cle => $libelle)
                        <option value="{{ $cle }}" @selected(old('mode_paiement') === $cle)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="recu">Montant reçu</label>
                <input type="number" id="recu" min="0" step="1" inputmode="numeric">
            </div>
        </div>
        <input type="hidden" name="montant_paye" id="montant_paye" value="0">

        <div class="resume"><span>Monnaie à rendre</span><b id="monnaie">0 F</b></div>
        <div class="resume"><span>Reste à crédit</span><b id="credit" class="negatif">0 F</b></div>

        <button type="submit" class="btn btn-ambre valider" id="btn-valider" disabled>Valider la vente</button>
    </form>
</div>
<a href="#form-vente" class="barre-panier"><span id="barre-nb">Panier vide</span><span id="barre-total">0 F</span></a>
@endsection

@push('scripts')
@php
    $catalogue = $produits->mapWithKeys(fn ($p) => [$p->id => ['nom' => $p->nom, 'prix' => $p->prix_vente, 'stock' => $p->stock]]);
    $panierPrecedent = collect(old('lignes', []))->map(fn ($l) => [(string) ($l['produit_id'] ?? ''), (int) ($l['quantite'] ?? 0)])->values();
@endphp
<script>
(function () {
    const produits = @json($catalogue);
    const panier = new Map(); // id -> quantité
    // Après une erreur, on retrouve le panier tel qu'il était
    @json($panierPrecedent).forEach(([id, q]) => { if (produits[id] && q > 0) panier.set(id, Math.min(q, produits[id].stock)); });
    let recuModifie = false;

    const fmt = n => new Intl.NumberFormat('fr-FR').format(n).replace(/ | /g, ' ') + ' F';
    const $ = id => document.getElementById(id);

    function total() {
        let t = 0;
        panier.forEach((q, id) => t += q * produits[id].prix);
        return t;
    }

    function ajouter(id) {
        const q = (panier.get(id) || 0) + 1;
        if (q > produits[id].stock) { alert('Stock insuffisant : il reste ' + produits[id].stock + '.'); return; }
        panier.set(id, q);
        afficher();
    }

    function changer(id, q) {
        q = Math.max(0, Math.min(parseInt(q) || 0, produits[id].stock));
        if (q === 0) panier.delete(id); else panier.set(id, q);
        afficher();
    }

    function afficher() {
        const tbody = $('lignes');
        tbody.innerHTML = '';
        let i = 0;
        panier.forEach((q, id) => {
            const p = produits[id];
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' + p.nom.replace(/</g, '&lt;') + '<div class="aide">' + fmt(p.prix) + '</div>'
                + '<input type="hidden" name="lignes[' + i + '][produit_id]" value="' + id + '">'
                + '<input type="hidden" name="lignes[' + i + '][quantite]" value="' + q + '"></td>'
                + '<td><div class="qte"><button type="button" data-moins>−</button>'
                + '<input type="number" min="0" value="' + q + '" data-saisie><button type="button" data-plus>+</button></div></td>'
                + '<td class="n">' + fmt(q * p.prix) + '</td>';
            tr.querySelector('[data-moins]').onclick = () => changer(id, q - 1);
            tr.querySelector('[data-plus]').onclick = () => changer(id, q + 1);
            tr.querySelector('[data-saisie]').onchange = e => changer(id, e.target.value);
            tbody.appendChild(tr);
            i++;
        });
        $('panier-vide').style.display = panier.size ? 'none' : '';
        const t = total();
        $('total').textContent = fmt(t);
        $('barre-total').textContent = fmt(t);
        let nb = 0; panier.forEach(q => nb += q);
        $('barre-nb').textContent = nb ? 'Voir le panier (' + nb + ' article' + (nb > 1 ? 's' : '') + ')' : 'Panier vide';
        if (!recuModifie) $('recu').value = t;
        $('btn-valider').disabled = panier.size === 0;
        calculer();
    }

    function calculer() {
        const t = total();
        const recu = parseInt($('recu').value) || 0;
        $('montant_paye').value = Math.min(recu, t);
        $('monnaie').textContent = fmt(Math.max(recu - t, 0));
        $('credit').textContent = fmt(Math.max(t - recu, 0));
    }

    $('recu').addEventListener('input', () => { recuModifie = true; calculer(); });

    document.querySelectorAll('.tuile').forEach(b => b.addEventListener('click', () => ajouter(b.dataset.id)));

    const recherche = $('recherche');
    recherche.addEventListener('input', () => {
        const q = recherche.value.trim().toLowerCase();
        document.querySelectorAll('.tuile').forEach(b => b.style.display = b.dataset.cherche.includes(q) ? '' : 'none');
    });
    // Lecteur de code-barres : il tape le code puis "Entrée"
    recherche.addEventListener('keydown', e => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const q = recherche.value.trim().toLowerCase();
        const visibles = [...document.querySelectorAll('.tuile')].filter(b => !b.disabled && b.style.display !== 'none');
        const exact = visibles.find(b => b.dataset.code && b.dataset.code.toLowerCase() === q);
        const choix = exact || (visibles.length === 1 ? visibles[0] : null);
        if (choix) {
            ajouter(choix.dataset.id);
            recherche.value = '';
            recherche.dispatchEvent(new Event('input'));
        }
    });

    $('form-vente').addEventListener('submit', e => {
        calculer();
        const t = total(), paye = parseInt($('montant_paye').value) || 0;
        if (paye < t && !$('client_id').value) {
            e.preventDefault();
            alert('Paiement incomplet : choisissez le client pour enregistrer le reste en crédit.');
            return;
        }
        $('btn-valider').disabled = true;
    });

    afficher();
})();
</script>
@endpush
