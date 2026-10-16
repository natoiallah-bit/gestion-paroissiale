import Dexie from 'dexie';

// Base locale : tout fonctionne sans connexion, le serveur est mis à jour à la synchronisation.
export const db = new Dexie('gestion-paroissiale');

db.version(1).stores({
  reglages: 'cle',
  boutique: 'uuid',
  utilisateurs: 'uuid',
  categories: 'uuid, nom',
  fournisseurs: 'uuid, nom',
  produits: 'uuid, nom, code, categorie_uuid',
  clients: 'uuid, nom',
  ventes: 'uuid, created_at, client_uuid, statut',
  remboursements: 'uuid, client_uuid, created_at',
  depenses: 'uuid, date_depense',
  // Opérations faites ici et pas encore envoyées au serveur
  envois: '++id, table, uuid',
  // Opérations refusées par le serveur, affichées dans l'écran Synchronisation
  rejets: '++id',
});

export const TABLES_DONNEES = ['boutique', 'utilisateurs', 'categories', 'fournisseurs', 'produits', 'clients', 'ventes', 'remboursements', 'depenses'];

export async function lire(cle, defaut = null) {
  const r = await db.reglages.get(cle);
  return r ? r.valeur : defaut;
}

export async function ecrire(cle, valeur) {
  await db.reglages.put({ cle, valeur });
}

export function uuid() {
  if (globalThis.crypto?.randomUUID) return crypto.randomUUID();
  // Repli pour les anciens Android
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
  });
}
