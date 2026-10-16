import { appeler, normaliserServeur } from './api.js';
import { db, ecrire, lire, TABLES_DONNEES, uuid } from './db.js';

// Session = serveur + jeton + utilisateur + appareil, gardés dans la base locale
export async function lireSession() {
  const jeton = await lire('jeton');
  if (!jeton) return null;
  return {
    serveur: await lire('serveur'),
    jeton,
    utilisateur: await lire('utilisateur'),
    appareil: await lire('appareil'),
  };
}

export async function seConnecter({ serveur, email, password, nomAppareil }) {
  serveur = normaliserServeur(serveur);
  if (!serveur) throw new Error("Indiquez l'adresse du serveur.");

  const ancienServeur = await lire('serveur');
  const appareilUuid = (await lire('appareil_uuid')) || uuid();

  const r = await appeler(serveur, '/api/connexion', {
    email, password, appareil_uuid: appareilUuid, appareil_nom: nomAppareil || 'Appareil',
  });

  // Nouveau serveur : on repart d'une base vide (les envois en attente ont été vérifiés avant)
  if (ancienServeur && ancienServeur !== serveur) {
    await viderDonnees();
  }

  await ecrire('appareil_uuid', appareilUuid);
  await ecrire('serveur', serveur);
  await ecrire('jeton', r.jeton);
  await ecrire('utilisateur', r.utilisateur);
  await ecrire('appareil', r.appareil);
  return lireSession();
}

export async function seDeconnecter() {
  await db.reglages.delete('jeton');
}

export async function viderDonnees() {
  await db.transaction('rw', [...TABLES_DONNEES, 'envois', 'rejets', 'reglages'].map((t) => db.table(t)), async () => {
    for (const t of [...TABLES_DONNEES, 'envois', 'rejets']) await db.table(t).clear();
    await db.reglages.bulkDelete(['revision', 'derniere_synchro', 'compteur_ticket']);
  });
}

export const estGerant = (session) => session?.utilisateur?.role === 'gerant';
