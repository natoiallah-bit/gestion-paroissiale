import { db, ecrire, lire, uuid } from './db.js';
import { jourLocal } from './format.js';
import { synchroniserBientot } from './sync.js';

// Chaque action écrit dans la base locale ET ajoute une opération à envoyer au serveur.

async function envoyer(table, donnees, cle = donnees.uuid) {
  await db.envois.add({ table, uuid: cle, donnees, date: new Date().toISOString() });
  synchroniserBientot();
}

const maintenant = () => new Date().toISOString();

/**
 * Stock affiché = stock reçu du serveur + opérations locales pas encore envoyées.
 */
export async function ecartsDeStock() {
  const ecarts = {};
  const ajouter = (produit, q) => { ecarts[produit] = (ecarts[produit] || 0) + q; };
  for (const e of await db.envois.toArray()) {
    if (e.table === 'ventes') e.donnees.lignes.forEach((l) => ajouter(l.produit_uuid, -l.quantite));
    if (e.table === 'mouvements') ajouter(e.donnees.produit_uuid, e.donnees.quantite);
    if (e.table === 'annulations') {
      const vente = await db.ventes.get(e.donnees.vente_uuid);
      vente?.lignes.forEach((l) => ajouter(l.produit_uuid, l.quantite));
    }
  }
  return ecarts;
}

export async function produitsAvecStock({ inactifs = false } = {}) {
  const [produits, ecarts] = await Promise.all([db.produits.orderBy('nom').toArray(), ecartsDeStock()]);
  return produits
    .filter((p) => inactifs || p.actif)
    .map((p) => ({ ...p, stock: (p.stock || 0) + (ecarts[p.uuid] || 0) }));
}

export async function enregistrerVente({ lignes, clientUuid, montantPaye, mode, session }) {
  const produits = await db.produits.bulkGet(lignes.map((l) => l.produit_uuid));
  const lignesVente = lignes.map((l, i) => {
    const p = produits[i];
    return {
      uuid: uuid(), produit_uuid: p.uuid, designation: p.nom, quantite: l.quantite,
      prix_unitaire: p.prix_vente, prix_achat_unitaire: p.prix_achat, total: l.quantite * p.prix_vente,
    };
  });
  const total = lignesVente.reduce((s, l) => s + l.total, 0);
  const paye = Math.min(Math.max(Math.round(montantPaye) || 0, 0), total);
  if (paye < total && !clientUuid) throw new Error('Paiement incomplet : choisissez le client pour enregistrer le reste en crédit.');

  // Numéro lisible et unique : code de l'appareil + compteur local (A1-000012)
  const compteur = (await lire('compteur_ticket', 0)) + 1;
  await ecrire('compteur_ticket', compteur);
  const numero = `${session.appareil.code}-${String(compteur).padStart(6, '0')}`;

  const vente = {
    uuid: uuid(), numero, client_uuid: clientUuid || null, user_uuid: session.utilisateur.uuid,
    total, montant_paye: paye, mode_paiement: mode, statut: 'validee', created_at: maintenant(), lignes: lignesVente,
  };
  await db.transaction('rw', db.ventes, db.envois, async () => {
    await db.ventes.put(vente);
    await envoyer('ventes', {
      uuid: vente.uuid, numero, client_uuid: vente.client_uuid, montant_paye: paye, mode_paiement: mode,
      created_at: vente.created_at,
      lignes: lignesVente.map(({ uuid: u, produit_uuid, designation, quantite, prix_unitaire }) => ({ uuid: u, produit_uuid, designation, quantite, prix_unitaire })),
    });
  });
  return vente;
}

export async function annulerVente(vente, motif) {
  await db.transaction('rw', db.ventes, db.envois, async () => {
    await db.ventes.update(vente.uuid, { statut: 'annulee', motif_annulation: motif });
    await envoyer('annulations', { vente_uuid: vente.uuid, motif }, vente.uuid);
  });
}

export async function enregistrerClient(client) {
  const fiche = { uuid: client.uuid || uuid(), nom: client.nom.trim(), telephone: client.telephone || null, adresse: client.adresse || null };
  if (!fiche.nom) throw new Error('Le nom est obligatoire.');
  await db.clients.put(fiche);
  await envoyer('clients', fiche);
  return fiche;
}

export async function detteClient(clientUuid) {
  const [ventes, remboursements] = await Promise.all([
    db.ventes.where('client_uuid').equals(clientUuid).toArray(),
    db.remboursements.where('client_uuid').equals(clientUuid).toArray(),
  ]);
  const credit = ventes.filter((v) => v.statut === 'validee').reduce((s, v) => s + v.total - v.montant_paye, 0);
  return credit - remboursements.reduce((s, r) => s + r.montant, 0);
}

export async function dettesParClient() {
  const dettes = {};
  for (const v of await db.ventes.toArray()) {
    if (v.client_uuid && v.statut === 'validee') dettes[v.client_uuid] = (dettes[v.client_uuid] || 0) + v.total - v.montant_paye;
  }
  for (const r of await db.remboursements.toArray()) dettes[r.client_uuid] = (dettes[r.client_uuid] || 0) - r.montant;
  return dettes;
}

export async function rembourser({ clientUuid, montant, mode, note, session }) {
  const dette = await detteClient(clientUuid);
  montant = Math.round(montant);
  if (!(montant > 0)) throw new Error('Montant invalide.');
  if (montant > dette) throw new Error('Le montant dépasse la dette du client.');
  const r = {
    uuid: uuid(), client_uuid: clientUuid, user_uuid: session.utilisateur.uuid, montant,
    mode_paiement: mode, note: note || null, created_at: maintenant(),
  };
  await db.remboursements.put(r);
  await envoyer('remboursements', { uuid: r.uuid, client_uuid: clientUuid, montant, mode_paiement: mode, note: r.note, created_at: r.created_at });
}

// ——— Gérant ———

export async function enregistrerProduit(produit, stockInitial = 0) {
  const existant = produit.uuid ? await db.produits.get(produit.uuid) : null;
  const fiche = {
    uuid: produit.uuid || uuid(),
    nom: (produit.nom || '').trim(),
    code: (produit.code || '').trim() || null,
    categorie_uuid: produit.categorie_uuid || null,
    unite: produit.unite || 'pièce',
    prix_achat: Math.round(produit.prix_achat) || 0,
    prix_vente: Math.round(produit.prix_vente) || 0,
    seuil_alerte: Math.round(produit.seuil_alerte) || 0,
    actif: produit.actif !== false,
  };
  if (!fiche.nom) throw new Error('Le nom est obligatoire.');
  if (fiche.code) {
    const meme = await db.produits.where('code').equals(fiche.code).first();
    if (meme && meme.uuid !== fiche.uuid) throw new Error(`Le code ${fiche.code} est déjà utilisé par « ${meme.nom} ».`);
  }
  await db.produits.put({ ...fiche, stock: existant?.stock ?? 0 });
  await envoyer('produits', fiche);
  if (stockInitial > 0) await mouvement(fiche.uuid, 'entree', stockInitial, { prix_achat_unitaire: fiche.prix_achat, note: 'Stock initial' });
  return fiche;
}

async function mouvement(produitUuid, type, quantite, extra = {}) {
  await envoyer('mouvements', { uuid: uuid(), produit_uuid: produitUuid, type, quantite, created_at: maintenant(), ...extra });
}

export async function entreeStock({ lignes, fournisseurUuid, note, majPrix, depense }) {
  let total = 0;
  for (const l of lignes) {
    await mouvement(l.produit_uuid, 'entree', l.quantite, {
      prix_achat_unitaire: l.prix_achat, maj_prix: !!majPrix, fournisseur_uuid: fournisseurUuid || null, note: note || null,
    });
    if (majPrix) await db.produits.update(l.produit_uuid, { prix_achat: l.prix_achat });
    total += l.quantite * l.prix_achat;
  }
  if (depense && total > 0) {
    await enregistrerDepense({ libelle: 'Achat de marchandise' + (note ? ' — ' + note : ''), categorie: 'Achat marchandise', montant: total, date_depense: jourLocal(), fournisseur_uuid: fournisseurUuid });
  }
  return total;
}

export async function ajusterStock(produit, stockReel, note) {
  const ecart = Math.round(stockReel) - produit.stock;
  if (ecart !== 0) await mouvement(produit.uuid, 'ajustement', ecart, { note });
}

export async function enregistrerDepense(d) {
  const fiche = {
    uuid: d.uuid || uuid(), libelle: d.libelle.trim(), categorie: d.categorie, montant: Math.round(d.montant),
    date_depense: d.date_depense, fournisseur_uuid: d.fournisseur_uuid || null,
  };
  if (!fiche.libelle || !(fiche.montant > 0)) throw new Error('Libellé et montant obligatoires.');
  await db.depenses.put(fiche);
  await envoyer('depenses', fiche);
}

export async function enregistrerCategorie(nom) {
  const fiche = { uuid: uuid(), nom: nom.trim() };
  if (!fiche.nom) return;
  await db.categories.put(fiche);
  await envoyer('categories', fiche);
  return fiche;
}

export async function enregistrerFournisseur(f) {
  const fiche = { uuid: f.uuid || uuid(), nom: f.nom.trim(), telephone: f.telephone || null, adresse: f.adresse || null, note: f.note || null };
  if (!fiche.nom) throw new Error('Le nom est obligatoire.');
  await db.fournisseurs.put(fiche);
  await envoyer('fournisseurs', fiche);
  return fiche;
}

// Chiffres d'une période à partir des données locales (dates "AAAA-MM-JJ" incluses)
export async function bilan(du, au) {
  const ventes = (await db.ventes.toArray()).filter((v) => v.statut === 'validee' && jourLocal(new Date(v.created_at)) >= du && jourLocal(new Date(v.created_at)) <= au);
  const remboursements = (await db.remboursements.toArray()).filter((r) => jourLocal(new Date(r.created_at)) >= du && jourLocal(new Date(r.created_at)) <= au);
  const depenses = (await db.depenses.toArray()).filter((d) => d.date_depense >= du && d.date_depense <= au);

  const ca = ventes.reduce((s, v) => s + v.total, 0);
  const paye = ventes.reduce((s, v) => s + v.montant_paye, 0);
  const cout = ventes.reduce((s, v) => s + v.lignes.reduce((t, l) => t + l.quantite * (l.prix_achat_unitaire || 0), 0), 0);
  const fonctionnement = depenses.filter((d) => d.categorie !== 'Achat marchandise').reduce((s, d) => s + d.montant, 0);
  const rembourse = remboursements.reduce((s, r) => s + r.montant, 0);

  return {
    nbVentes: ventes.length, chiffreAffaires: ca, coutAchats: cout, marge: ca - cout,
    depenses: fonctionnement, benefice: ca - cout - fonctionnement,
    creditsAccordes: ca - paye, remboursements: rembourse, encaisse: paye + rembourse,
  };
}
