import { appeler, ErreurReseau, ErreurSession } from './api.js';
import { db, ecrire, lire, TABLES_DONNEES } from './db.js';
import { CLES } from './ressources.js';
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

// Pour chaque fiche, seule la dernière version compte ; les tables partent dans l'ordre des
// dépendances (paroisse → CEB → fidèle → sacrement), comme le serveur les traite.
export function regrouper(envois) {
  const changements = {};
  for (const cle of CLES) {
    const parUuid = new Map();
    for (const e of envois) if (e.table === cle) parUuid.set(e.donnees.uuid, e.donnees);
    if (parUuid.size) changements[cle] = [...parUuid.values()];
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
      // Une fiche refusée par le serveur ne doit pas rester modifiée ici : on retélécharge tout
      if (reponse.rejets?.length && changements !== null) {
        reponse = { ...reponse, revision: 0, encore: true };
        await ecrire('revision', 0);
      }
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
        // Une fiche créée ici et refusée n'existe pas sur le serveur : on la retire aussi d'ici
        if (CLES.includes(r.table) && r.uuid) {
          const locale = await db.table(r.table).get(r.uuid);
          if (locale?._local) await db.table(r.table).delete(r.uuid);
        }
      }
    }

    for (const [table, lignes] of Object.entries(reponse.donnees || {})) {
      if (lignes.length) await db.table(table).bulkPut(lignes);
    }
    for (const s of reponse.suppressions || []) {
      const table = s.table_nom === 'users' ? 'utilisateurs' : s.table_nom === 'vicariat' ? 'vicariat' : s.table_nom;
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
