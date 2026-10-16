import { appeler, ErreurReseau, ErreurSession } from './api.js';
import { db, ecrire, lire, TABLES_DONNEES } from './db.js';
import { lireSession } from './session.js';

/**
 * Synchronisation :
 * 1. on envoie les opérations en attente (table envois) ;
 * 2. le serveur répond avec tout ce qui a changé depuis notre dernière révision ;
 * 3. on enregistre la réponse et on retire les envois traités, dans la même transaction.
 * Si la connexion coupe, rien n'est perdu : les envois restent et seront renvoyés
 * (le serveur reconnaît les uuid déjà reçus).
 */

const abonnes = new Set();
let etat = { enCours: false, erreur: null, horsLigne: false };

export function surEtat(f) {
  abonnes.add(f);
  f(etat);
  return () => abonnes.delete(f);
}

function changerEtat(modif) {
  etat = { ...etat, ...modif };
  abonnes.forEach((f) => f(etat));
}

// Tables envoyées dans l'ordre attendu par le serveur ; pour les fiches, seule la dernière version compte
const FICHES = ['categories', 'fournisseurs', 'clients', 'produits', 'depenses'];

export function regrouper(envois) {
  const changements = {};
  for (const e of envois) {
    (changements[e.table] ||= []).push(e.donnees);
  }
  for (const t of FICHES) {
    if (!changements[t]) continue;
    const parUuid = new Map();
    for (const d of changements[t]) parUuid.set(d.uuid, d);
    changements[t] = [...parUuid.values()];
  }
  return changements;
}

let promesse = null;

export function synchroniser() {
  if (!promesse) {
    promesse = executer().finally(() => { promesse = null; });
  }
  return promesse;
}

async function executer() {
  const session = await lireSession();
  if (!session) return;
  changerEtat({ enCours: true, erreur: null });

  try {
    let depuis = await lire('revision', 0);
    const envois = await db.envois.orderBy('id').toArray();
    let changements = regrouper(envois);
    let reponse;

    do {
      reponse = await appeler(session.serveur, '/api/synchroniser', { depuis, changements }, session.jeton, 60000);
      await appliquer(reponse, changements === null ? [] : envois);
      depuis = reponse.revision;
      changements = null; // les envois ne partent qu'avec le premier morceau
    } while (reponse.encore);

    if (reponse.utilisateur) await ecrire('utilisateur', { ...session.utilisateur, ...reponse.utilisateur });
    await ecrire('derniere_synchro', new Date().toISOString());
    changerEtat({ enCours: false, horsLigne: false });
    return reponse;
  } catch (e) {
    changerEtat({
      enCours: false,
      horsLigne: e instanceof ErreurReseau,
      erreur: e instanceof ErreurReseau ? null : e.message,
      sessionExpiree: e instanceof ErreurSession,
    });
    if (!(e instanceof ErreurReseau)) throw e;
  }
}

export async function appliquer(reponse, envoisTraites) {
  const tables = [...TABLES_DONNEES, 'envois', 'rejets', 'reglages'].map((t) => db.table(t));
  await db.transaction('rw', tables, async () => {
    if (envoisTraites.length) {
      await db.envois.bulkDelete(envoisTraites.map((e) => e.id));
      for (const r of reponse.rejets || []) {
        const envoi = envoisTraites.find((e) => e.table === r.table && (e.uuid === r.uuid));
        await db.rejets.add({ ...r, date: new Date().toISOString(), donnees: envoi?.donnees ?? null });
        // Une vente refusée n'existe pas sur le serveur : on la retire aussi d'ici
        if (r.table === 'ventes') await db.ventes.delete(r.uuid);
      }
    }

    for (const [table, lignes] of Object.entries(reponse.donnees || {})) {
      // Dates au même format que celles créées ici, pour que les recherches par période marchent
      for (const l of lignes) if (l.created_at) l.created_at = new Date(l.created_at).toISOString();
      if (lignes.length) await db.table(table).bulkPut(lignes);
    }
    for (const s of reponse.suppressions || []) {
      const table = s.table_nom === 'users' ? 'utilisateurs' : s.table_nom;
      if (TABLES_DONNEES.includes(table)) await db.table(table).delete(s.uuid);
    }
    await db.reglages.put({ cle: 'revision', valeur: reponse.revision });
  });
}

// Synchronisation automatique : au démarrage, au retour de la connexion, puis toutes les minutes
let minuteur = null;
let differee = null;

export function demarrerAuto() {
  const tenter = () => synchroniser().catch(() => {});
  tenter();
  window.addEventListener('online', tenter);
  clearInterval(minuteur);
  minuteur = setInterval(tenter, 60000);
}

// Après une vente : on envoie quelques secondes plus tard (plusieurs ventes d'affilée = un seul envoi)
export function synchroniserBientot() {
  clearTimeout(differee);
  differee = setTimeout(() => synchroniser().catch(() => {}), 3000);
}
