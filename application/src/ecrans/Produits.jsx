import { useState } from 'preact/hooks';
import { useApp } from '../App.jsx';
import { ajusterStock, enregistrerCategorie, enregistrerFournisseur, enregistrerProduit, entreeStock, produitsAvecStock } from '../actions.js';
import { useRequete } from '../crochets.js';
import { db } from '../db.js';
import { fcfa } from '../format.js';

const badgeStock = (p) => (
  <span class={'badge ' + (p.stock <= 0 ? 'badge-rouge' : p.stock <= p.seuil_alerte ? 'badge-ambre' : 'badge-vert')}>{p.stock} {p.unite}</span>
);

export function Produits() {
  const { naviguer } = useApp();
  const [q, setQ] = useState('');
  const [alerte, setAlerte] = useState(false);
  const donnees = useRequete(async () => ({
    produits: await produitsAvecStock({ inactifs: true }),
    categories: Object.fromEntries((await db.categories.toArray()).map((c) => [c.uuid, c.nom])),
  }), [], null);
  if (!donnees) return null;

  const texte = q.trim().toLowerCase();
  const liste = donnees.produits
    .filter((p) => !texte || (p.nom + ' ' + (p.code || '')).toLowerCase().includes(texte))
    .filter((p) => !alerte || (p.actif && p.stock <= p.seuil_alerte));
  const valeur = donnees.produits.filter((p) => p.actif && p.stock > 0).reduce((s, p) => s + p.stock * p.prix_achat, 0);

  return (
    <>
      <div class="entete">
        <h1>Produits</h1>
        <div class="actions">
          <button class="btn btn-leger" onClick={() => naviguer('entree')}>Entrée de marchandise</button>
          <button class="btn btn-ambre" onClick={() => naviguer('produitForm')}>+ Nouveau produit</button>
        </div>
      </div>
      <div class="champs" style="margin-bottom:10px; align-items:center">
        <div style="flex:2"><input type="search" placeholder="Nom ou code" value={q} onInput={(e) => setQ(e.target.value)} /></div>
        <label class="case" style="margin:0"><input type="checkbox" checked={alerte} onChange={(e) => setAlerte(e.target.checked)} /> Stock bas seulement</label>
      </div>
      <p class="aide" style="margin-bottom:10px">Valeur du stock (prix d'achat) : <b>{fcfa(valeur)}</b></p>
      <div class="carte tableau">
        <table>
          <thead><tr><th>Produit</th><th>Catégorie</th><th class="n">Achat</th><th class="n">Vente</th><th class="n">Stock</th></tr></thead>
          <tbody>
            {liste.map((p) => (
              <tr key={p.uuid} class="clic" onClick={() => naviguer('produit', { uuid: p.uuid })}>
                <td><b>{p.nom}</b>{p.code && <div class="aide">{p.code}</div>}{!p.actif && <span class="badge badge-gris">Inactif</span>}</td>
                <td>{donnees.categories[p.categorie_uuid] || '—'}</td>
                <td class="n">{fcfa(p.prix_achat)}</td><td class="n">{fcfa(p.prix_vente)}</td>
                <td class="n">{badgeStock(p)}</td>
              </tr>
            ))}
            {!liste.length && <tr><td colSpan={5} class="vide">Aucun produit.</td></tr>}
          </tbody>
        </table>
      </div>
    </>
  );
}

export function ProduitForm({ uuid }) {
  const { naviguer } = useApp();
  const donnees = useRequete(async () => ({
    produit: uuid ? await db.produits.get(uuid) : null,
    categories: await db.categories.orderBy('nom').toArray(),
  }), [uuid], null);
  const [f, setF] = useState(null);
  const [stockInitial, setStockInitial] = useState(0);
  const [erreur, setErreur] = useState(null);
  if (!donnees) return null;

  const v = f || donnees.produit || { nom: '', code: '', categorie_uuid: '', unite: 'pièce', prix_achat: 0, prix_vente: '', seuil_alerte: 5, actif: true };
  const champ = (cle, nombre) => ({ value: v[cle] ?? '', onInput: (e) => setF({ ...v, [cle]: nombre ? (e.target.value === '' ? '' : Number(e.target.value)) : e.target.value }) });

  const nouvelleCategorie = async () => {
    const nom = prompt('Nom de la nouvelle catégorie');
    if (nom) { const c = await enregistrerCategorie(nom); if (c) setF({ ...v, categorie_uuid: c.uuid }); }
  };

  const valider = async (e) => {
    e.preventDefault();
    try {
      const p = await enregistrerProduit(v, uuid ? 0 : Number(stockInitial) || 0);
      naviguer('produit', { uuid: p.uuid }, 'Produit enregistré.');
    } catch (err) { setErreur(err.message); }
  };

  return (
    <>
      <h1>{donnees.produit ? `Modifier « ${donnees.produit.nom} »` : 'Nouveau produit'}</h1>
      <form class="carte formulaire" onSubmit={valider}>
        {erreur && <div class="alerte alerte-erreur">{erreur}</div>}
        <label>Nom du produit</label><input type="text" required maxLength={150} {...champ('nom')} />
        <div class="champs">
          <div><label>Code / code-barres</label><input type="text" maxLength={60} {...champ('code')} /></div>
          <div>
            <label>Catégorie</label>
            <select value={v.categorie_uuid || ''} onChange={(e) => setF({ ...v, categorie_uuid: e.target.value })}>
              <option value="">— Aucune —</option>
              {donnees.categories.map((c) => <option key={c.uuid} value={c.uuid}>{c.nom}</option>)}
            </select>
            <div class="aide"><a onClick={nouvelleCategorie}>+ Nouvelle catégorie</a></div>
          </div>
        </div>
        <div class="champs">
          <div><label>Prix d'achat (F)</label><input type="number" min="0" required {...champ('prix_achat', true)} /></div>
          <div><label>Prix de vente (F)</label><input type="number" min="0" required {...champ('prix_vente', true)} /></div>
        </div>
        <div class="champs">
          <div><label>Unité</label><input type="text" required list="unites" maxLength={30} {...champ('unite')} />
            <datalist id="unites"><option value="pièce" /><option value="kg" /><option value="litre" /><option value="sac" /><option value="carton" /><option value="paquet" /></datalist></div>
          <div><label>Alerte de stock à</label><input type="number" min="0" required {...champ('seuil_alerte', true)} /></div>
          {!uuid && <div><label>Stock de départ</label><input type="number" min="0" value={stockInitial} onInput={(e) => setStockInitial(e.target.value)} /></div>}
        </div>
        <label class="case" style="margin-top:12px"><input type="checkbox" checked={v.actif !== false} onChange={(e) => setF({ ...v, actif: e.target.checked })} /> En vente (visible à la caisse)</label>
        <div class="actions" style="margin-top:14px">
          <button class="btn btn-ambre">Enregistrer</button>
          <button type="button" class="btn btn-leger" onClick={() => naviguer(uuid ? 'produit' : 'produits', uuid ? { uuid } : {})}>Annuler</button>
        </div>
      </form>
    </>
  );
}

export function ProduitDetail({ uuid }) {
  const { naviguer } = useApp();
  const produit = useRequete(async () => (await produitsAvecStock({ inactifs: true })).find((p) => p.uuid === uuid) || null, [uuid], undefined);
  const [reel, setReel] = useState('');
  const [note, setNote] = useState('');
  if (produit === undefined) return null;
  if (!produit) return <div class="vide">Produit introuvable.</div>;

  const ajuster = async (e) => {
    e.preventDefault();
    await ajusterStock(produit, Number(reel), note);
    setReel(''); setNote('');
    naviguer('produit', { uuid }, 'Stock ajusté.');
  };

  return (
    <>
      <div class="entete">
        <h1>{produit.nom} {!produit.actif && <span class="badge badge-gris">Inactif</span>}</h1>
        <div class="actions">
          <button class="btn btn-ambre" onClick={() => naviguer('entree', { produit: uuid })}>Entrée de stock</button>
          <button class="btn btn-leger" onClick={() => naviguer('produitForm', { uuid })}>Modifier</button>
        </div>
      </div>
      <div class="grille">
        <div class="stat"><div class="lib">Stock</div><div class="val">{badgeStock(produit)}</div><div class="det">alerte à {produit.seuil_alerte}</div></div>
        <div class="stat"><div class="lib">Prix de vente</div><div class="val">{fcfa(produit.prix_vente)}</div></div>
        <div class="stat"><div class="lib">Prix d'achat</div><div class="val">{fcfa(produit.prix_achat)}</div><div class="det">marge {fcfa(produit.prix_vente - produit.prix_achat)}</div></div>
      </div>
      <form class="carte formulaire" onSubmit={ajuster}>
        <h2>Ajuster le stock (inventaire, casse, perte…)</h2>
        <div class="champs">
          <div><label>Stock réellement compté</label><input type="number" min="0" required placeholder={String(produit.stock)} value={reel} onInput={(e) => setReel(e.target.value)} /></div>
          <div style="flex:2"><label>Raison</label><input type="text" required maxLength={255} value={note} onInput={(e) => setNote(e.target.value)} placeholder="Inventaire, article cassé…" /></div>
        </div>
        <button class="btn" style="margin-top:10px">Enregistrer l'ajustement</button>
      </form>
      <p class="aide">L'historique complet des mouvements est consultable sur le site web du serveur.</p>
    </>
  );
}

export function EntreeStock({ produit }) {
  const { naviguer } = useApp();
  const donnees = useRequete(async () => ({
    produits: await produitsAvecStock(),
    fournisseurs: await db.fournisseurs.orderBy('nom').toArray(),
  }), [], null);
  const [lignes, setLignes] = useState(null);
  const [fournisseur, setFournisseur] = useState('');
  const [note, setNote] = useState('');
  const [majPrix, setMajPrix] = useState(true);
  const [depense, setDepense] = useState(false);
  if (!donnees) return null;

  const prixDe = (u) => donnees.produits.find((p) => p.uuid === u)?.prix_achat ?? 0;
  const liste = lignes ?? [{ produit_uuid: produit || '', quantite: 1, prix_achat: produit ? prixDe(produit) : 0 }];
  const modifier = (i, modif) => setLignes(liste.map((l, j) => (j === i ? { ...l, ...modif } : l)));
  const total = liste.reduce((s, l) => s + (Number(l.quantite) || 0) * (Number(l.prix_achat) || 0), 0);

  const nouveauFournisseur = async () => {
    const nom = prompt('Nom du fournisseur');
    if (nom) { const f = await enregistrerFournisseur({ nom }); setFournisseur(f.uuid); }
  };

  const valider = async (e) => {
    e.preventDefault();
    const valides = liste.filter((l) => l.produit_uuid && Number(l.quantite) > 0).map((l) => ({ ...l, quantite: Number(l.quantite), prix_achat: Number(l.prix_achat) || 0 }));
    if (!valides.length) return;
    const t = await entreeStock({ lignes: valides, fournisseurUuid: fournisseur, note, majPrix, depense });
    naviguer('produits', {}, `Entrée de stock enregistrée (${fcfa(t)}).`);
  };

  return (
    <>
      <h1>Entrée de marchandise</h1>
      <form class="carte" onSubmit={valider}>
        <div class="champs">
          <div>
            <label>Fournisseur</label>
            <select value={fournisseur} onChange={(e) => setFournisseur(e.target.value)}>
              <option value="">— Non précisé —</option>
              {donnees.fournisseurs.map((f) => <option key={f.uuid} value={f.uuid}>{f.nom}</option>)}
            </select>
            <div class="aide"><a onClick={nouveauFournisseur}>+ Nouveau fournisseur</a></div>
          </div>
          <div style="flex:2"><label>Note (n° de facture…)</label><input type="text" maxLength={255} value={note} onInput={(e) => setNote(e.target.value)} /></div>
        </div>
        <div class="tableau" style="margin-top:12px">
          <table>
            <thead><tr><th>Produit</th><th style="width:100px">Quantité</th><th style="width:130px">Prix d'achat</th><th class="n">Sous-total</th><th></th></tr></thead>
            <tbody>
              {liste.map((l, i) => (
                <tr key={i}>
                  <td><select required value={l.produit_uuid} onChange={(e) => modifier(i, { produit_uuid: e.target.value, prix_achat: prixDe(e.target.value) })}>
                    <option value="">— Choisir —</option>
                    {donnees.produits.map((p) => <option key={p.uuid} value={p.uuid}>{p.nom} (stock {p.stock})</option>)}
                  </select></td>
                  <td><input type="number" min="1" required value={l.quantite} onInput={(e) => modifier(i, { quantite: e.target.value })} /></td>
                  <td><input type="number" min="0" required value={l.prix_achat} onInput={(e) => modifier(i, { prix_achat: e.target.value })} /></td>
                  <td class="n">{fcfa((Number(l.quantite) || 0) * (Number(l.prix_achat) || 0))}</td>
                  <td><button type="button" class="btn btn-leger btn-petit" onClick={() => setLignes(liste.filter((_, j) => j !== i))}>✕</button></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <div class="actions" style="justify-content:space-between; margin-top:8px">
          <button type="button" class="btn btn-leger btn-petit" onClick={() => setLignes([...liste, { produit_uuid: '', quantite: 1, prix_achat: 0 }])}>+ Ajouter une ligne</button>
          <b>Total : {fcfa(total)}</b>
        </div>
        <label class="case" style="margin-top:12px"><input type="checkbox" checked={majPrix} onChange={(e) => setMajPrix(e.target.checked)} /> Mettre à jour le prix d'achat des produits</label>
        <label class="case"><input type="checkbox" checked={depense} onChange={(e) => setDepense(e.target.checked)} /> Enregistrer aussi le paiement comme dépense « Achat marchandise »</label>
        <button class="btn btn-ambre" style="margin-top:14px">Enregistrer l'entrée</button>
      </form>
    </>
  );
}
