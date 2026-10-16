import Dexie from 'dexie';
import { RESSOURCES } from './ressources.js';

// Base locale : tout fonctionne sans connexion, le serveur est mis à jour à la synchronisation.
export const db = new Dexie('gestion-paroissiale');

const tables = {
  reglages: 'cle',
  vicariat: 'uuid',
  utilisateurs: 'uuid',
  // Opérations faites ici et pas encore envoyées au serveur
  envois: '++id, table, uuid',
  // Opérations refusées par le serveur, affichées dans l'écran Synchronisation
  rejets: '++id',
};
for (const [cle, def] of Object.entries(RESSOURCES)) {
  const index = new Set(['uuid']);
  for (const c of def.champs) {
    if (c.type === 'ref' || c.type === 'date' || ['nom', 'titre', 'type', 'statut', 'sens'].includes(c.nom)) index.add(c.nom);
  }
  tables[cle] = [...index].join(', ');
}
db.version(1).stores(tables);

export const TABLES_DONNEES = ['vicariat', 'utilisateurs', ...Object.keys(RESSOURCES)];

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
