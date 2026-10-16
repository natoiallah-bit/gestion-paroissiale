@extends('layouts.app')
@section('title', 'Dépenses')

@section('content')
<h1>Dépenses</h1>

<form class="carte" method="POST" action="{{ route('depenses.store') }}">
    @csrf
    <h2>Nouvelle dépense</h2>
    <div class="ligne-champs">
        <div style="flex:2"><label for="libelle">Libellé</label><input type="text" id="libelle" name="libelle" value="{{ old('libelle') }}" required maxlength="200" placeholder="Loyer d'octobre, facture CIE…"></div>
        <div>
            <label for="categorie">Catégorie</label>
            <select id="categorie" name="categorie">
                @foreach(\App\Models\Depense::CATEGORIES as $c)<option @selected(old('categorie') === $c)>{{ $c }}</option>@endforeach
            </select>
        </div>
    </div>
    <div class="ligne-champs">
        <div><label for="montant">Montant (F)</label><input type="number" id="montant" name="montant" min="1" value="{{ old('montant') }}" required></div>
        <div><label for="date_depense">Date</label><input type="date" id="date_depense" name="date_depense" value="{{ old('date_depense', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required></div>
        <div>
            <label for="fournisseur_id">Fournisseur <span class="aide">(facultatif)</span></label>
            <select id="fournisseur_id" name="fournisseur_id">
                <option value="">—</option>
                @foreach($fournisseurs as $f)<option value="{{ $f->id }}" @selected(old('fournisseur_id') == $f->id)>{{ $f->nom }}</option>@endforeach
            </select>
        </div>
    </div>
    <button class="btn btn-ambre" style="margin-top:14px">Enregistrer</button>
</form>

<form class="filtres" method="GET">
    <div><label for="du">Du</label><input type="date" id="du" name="du" value="{{ $du->toDateString() }}"></div>
    <div><label for="au">Au</label><input type="date" id="au" name="au" value="{{ $au->toDateString() }}"></div>
    <button class="btn">Afficher</button>
</form>

<div class="grille">
    <div class="stat"><div class="lib">Total de la période</div><div class="val">{{ fcfa($depenses->sum('montant')) }}</div></div>
    @foreach($parCategorie->take(3) as $cat => $montant)
        <div class="stat"><div class="lib">{{ $cat }}</div><div class="val">{{ fcfa($montant) }}</div></div>
    @endforeach
</div>

<div class="carte tableau">
    <table>
        <thead><tr><th>Date</th><th>Libellé</th><th>Catégorie</th><th>Fournisseur</th><th class="n">Montant</th><th>Saisie par</th><th></th></tr></thead>
        <tbody>
        @forelse($depenses as $d)
            <tr>
                <td>{{ $d->date_depense->format('d/m/Y') }}</td>
                <td>{{ $d->libelle }}</td>
                <td>{{ $d->categorie }}</td>
                <td>{{ $d->fournisseur?->nom ?? '—' }}</td>
                <td class="n">{{ fcfa($d->montant) }}</td>
                <td>{{ $d->user->name }}</td>
                <td>
                    <form method="POST" action="{{ route('depenses.destroy', $d) }}" onsubmit="return confirm('Supprimer cette dépense ?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-leger btn-petit">Supprimer</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="vide">Aucune dépense sur cette période.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
