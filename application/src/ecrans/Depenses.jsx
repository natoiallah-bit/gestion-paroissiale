import { useState } from 'preact/hooks';
import { useApp } from '../App.jsx';
import { enregistrerDepense } from '../actions.js';
import { useRequete } from '../crochets.js';
import { db } from '../db.js';
import { CATEGORIES_DEPENSES, dateCourte, fcfa, jourLocal } from '../format.js';

export function Depenses() {
  const { naviguer } = useApp();
  const aujourdhui = jourLocal();
  const [du, setDu] = useState(aujourdhui.slice(0, 8) + '01');
  const [au, setAu] = useState(aujourdhui);
  const vide = { libelle: '', categorie: 'Loyer', montant: '', date_depense: aujourdhui, fournisseur_uuid: '' };
  const [f, setF] = useState(vide);
  const [erreur, setErreur] = useState(null);
  const donnees = useRequete(async () => ({
    depenses: await db.depenses.where('date_depense').between(du, au, true, true).reverse().toArray(),
    fournisseurs: await db.fournisseurs.orderBy('nom').toArray(),
  }), [du, au], null);
  if (!donnees) return null;
  const champ = (cle) => ({ value: f[cle], onInput: (e) => setF({ ...f, [cle]: e.target.value }) });
  const noms = Object.fromEntries(donnees.fournisseurs.map((x) => [x.uuid, x.nom]));

  const valider = async (e) => {
    e.preventDefault();
    try {
      await enregistrerDepense({ ...f, montant: Number(f.montant) });
      setF(vide);
      naviguer('depenses', {}, 'Dépense enregistrée.');
    } catch (err) { setErreur(err.message); }
  };

  return (
    <>
      <h1>Dépenses</h1>
      <form class="carte" onSubmit={valider}>
        <h2>Nouvelle dépense</h2>
        {erreur && <div class="alerte alerte-erreur">{erreur}</div>}
        <div class="champs">
          <div style="flex:2"><label>Libellé</label><input type="text" required maxLength={200} placeholder="Loyer d'octobre, facture d'électricité…" {...champ('libelle')} /></div>
          <div><label>Catégorie</label><select {...champ('categorie')} onChange={(e) => setF({ ...f, categorie: e.target.value })}>{CATEGORIES_DEPENSES.map((c) => <option key={c}>{c}</option>)}</select></div>
        </div>
        <div class="champs">
          <div><label>Montant (F)</label><input type="number" min="1" required {...champ('montant')} /></div>
          <div><label>Date</label><input type="date" required max={aujourdhui} {...champ('date_depense')} /></div>
          <div><label>Fournisseur</label><select value={f.fournisseur_uuid} onChange={(e) => setF({ ...f, fournisseur_uuid: e.target.value })}>
            <option value="">—</option>{donnees.fournisseurs.map((x) => <option key={x.uuid} value={x.uuid}>{x.nom}</option>)}</select></div>
        </div>
        <button class="btn btn-ambre" style="margin-top:12px">Enregistrer</button>
      </form>
      <div class="champs" style="max-width:420px; margin-bottom:10px">
        <div><label>Du</label><input type="date" value={du} onChange={(e) => setDu(e.target.value)} /></div>
        <div><label>Au</label><input type="date" value={au} onChange={(e) => setAu(e.target.value)} /></div>
      </div>
      <div class="grille"><div class="stat"><div class="lib">Total de la période</div><div class="val">{fcfa(donnees.depenses.reduce((s, d) => s + d.montant, 0))}</div></div></div>
      <div class="carte tableau">
        <table>
          <thead><tr><th>Date</th><th>Libellé</th><th>Catégorie</th><th>Fournisseur</th><th class="n">Montant</th></tr></thead>
          <tbody>
            {donnees.depenses.map((d) => (
              <tr key={d.uuid}><td>{dateCourte(d.date_depense + 'T12:00:00')}</td><td>{d.libelle}</td><td>{d.categorie}</td><td>{noms[d.fournisseur_uuid] || '—'}</td><td class="n">{fcfa(d.montant)}</td></tr>
            ))}
            {!donnees.depenses.length && <tr><td colSpan={5} class="vide">Aucune dépense sur cette période.</td></tr>}
          </tbody>
        </table>
      </div>
    </>
  );
}
