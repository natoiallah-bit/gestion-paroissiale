import { db, uuid } from './db.js';
import { jourLocal } from './format.js';
import { CATEGORIES_DEPENSES, CATEGORIES_RECETTES, estLimite, intitule, peutEcrire, RESSOURCES, SACREMENTS } from './ressources.js';
import { synchroniserBientot } from './sync.js';

// Chaque action écrit dans la base locale ET ajoute une opération à envoyer au serveur.
// Les contrôles reprennent ceux du serveur (App\Support\Enregistreur) pour répondre tout de suite,
// même sans connexion ; le serveur reste l'arbitre à la synchronisation.

async function envoyer(table, donnees) {
  await db.envois.add({ table, uuid: donnees.uuid, donnees, date: new Date().toISOString() });
  synchroniserBientot();
}

const UNIQUES = ['bapteme', 'eucharistie', 'confirmation', 'obseques'];

function normaliser(c, brut) {
  let v = brut;
  if (typeof v === 'string') v = v.trim();
  if (v === '' || v === undefined) v = null;
  if (v === null) return c.defaut !== undefined && brut === undefined ? c.defaut : null;
  if (c.type === 'entier' || c.type === 'montant') {
    v = Math.round(Number(v));
    if (!Number.isFinite(v)) throw new Error(`${c.label} : nombre invalide.`);
    if (v < (c.type === 'montant' ? 1 : 0)) throw new Error(`${c.label} : valeur trop petite.`);
  }
  if (c.type === 'choix' && !(v in c.options)) throw new Error(`${c.label} : choix invalide.`);
  if ((c.type === 'texte' || c.type === 'tel' || c.type === 'long') && String(v).length > (c.max || 255)) throw new Error(`${c.label} : trop long.`);
  if (c.type === 'date') v = String(v).slice(0, 10);
  return v;
}

/**
 * Crée ou modifie une fiche (fidèle, CEB, sacrement, opération…).
 * `valeurs` contient les champs du formulaire ; les références sont des uuid.
 */
export async function enregistrerFiche(cle, valeurs, session, existant = null) {
  const def = RESSOURCES[cle];
  const user = session.utilisateur;
  if (!peutEcrire(user.role, cle)) throw new Error("Votre compte n'a pas le droit de modifier ces données.");
  if (estLimite(user) && existant && (existant.secteur_uuid ?? null) !== user.secteur_uuid && def.portee !== 'derivee') {
    throw new Error('Cette fiche ne fait pas partie de votre secteur.');
  }

  const v = {};
  for (const c of def.champs) {
    let valeur = normaliser(c, valeurs[c.nom]);
    // Un compte rattaché à un secteur travaille toujours dans ce secteur
    if (c.nom === 'secteur_uuid' && estLimite(user) && ['obligatoire', 'facultative', 'centrale'].includes(def.portee)) valeur = user.secteur_uuid;
    if (valeur === null && c.requis) throw new Error(`${c.label} : obligatoire.`);
    v[c.nom] = valeur;
  }

  await verifier(cle, v, user, existant);

  const fiche = { ...(existant || {}), ...v, uuid: existant?.uuid || uuid() };
  if (!existant) fiche._local = true; // pas encore connue du serveur
  if (cle === 'sacrements') fiche.secteur_uuid = (await db.fideles.get(v.fidele_uuid)).secteur_uuid;
  // Le compte d'une CEB appartient au secteur de cette CEB
  if (cle === 'operations' && v.ceb_uuid) fiche.secteur_uuid = (await db.cebs.get(v.ceb_uuid)).secteur_uuid;

  await db.transaction('rw', db.table(cle), db.fideles, db.envois, async () => {
    await db.table(cle).put(fiche);
    // Des obsèques ferment la fiche du fidèle (le serveur fait de même)
    if (cle === 'sacrements' && v.type === 'obseques') await db.fideles.update(v.fidele_uuid, { statut: 'decede' });
    await envoyer(cle, { uuid: fiche.uuid, ...v });
  });
  return fiche;
}

async function verifier(cle, v, user, existant) {
  const aujourdhui = jourLocal();

  if (cle === 'fideles') {
    if (v.ceb_uuid) {
      const ceb = await db.cebs.get(v.ceb_uuid);
      if (!ceb || ceb.secteur_uuid !== v.secteur_uuid) throw new Error("Cette CEB n'appartient pas au secteur du fidèle.");
    }
    if (v.date_naissance && v.date_naissance > aujourdhui) throw new Error('La date de naissance est dans le futur.');
  }

  if (cle === 'sacrements') {
    const fidele = await db.fideles.get(v.fidele_uuid);
    if (!fidele) throw new Error('Fidèle introuvable.');
    if (estLimite(user) && fidele.secteur_uuid !== user.secteur_uuid) throw new Error('Ce fidèle ne fait pas partie de votre secteur.');
    const demain = jourLocal(new Date(Date.now() + 86400000));
    if (v.date_sacrement > demain) throw new Error('La date du sacrement est dans le futur.');
    if (fidele.date_naissance && v.date_sacrement < fidele.date_naissance) throw new Error('Le sacrement est antérieur à la naissance du fidèle.');
    if (UNIQUES.includes(v.type)) {
      const deja = (await db.sacrements.where('fidele_uuid').equals(v.fidele_uuid).toArray())
        .some((s) => s.type === v.type && s.uuid !== existant?.uuid);
      if (deja) throw new Error(`${SACREMENTS[v.type]} déjà enregistré pour ${intitule('fideles', fidele)}.`);
    }
  }

  if (cle === 'operations') {
    if (v.ceb_uuid) {
      const ceb = await db.cebs.get(v.ceb_uuid);
      if (!ceb) throw new Error('CEB introuvable.');
      if (v.secteur_uuid && ceb.secteur_uuid !== v.secteur_uuid) throw new Error("Cette CEB n'appartient pas à ce secteur.");
      if (estLimite(user) && ceb.secteur_uuid !== user.secteur_uuid) throw new Error('Cette CEB ne fait pas partie de votre secteur.');
    }
    const permises = v.sens === 'recette' ? CATEGORIES_RECETTES : CATEGORIES_DEPENSES;
    if (!permises.includes(v.categorie)) throw new Error(`Cette catégorie ne correspond pas à une ${v.sens === 'recette' ? 'recette' : 'dépense'}.`);
  }

  if (cle === 'depots' && v.date_depot > jourLocal(new Date(Date.now() + 86400000))) throw new Error('La date du dépôt est dans le futur.');

  if (cle === 'annonces' && v.date_fin && v.date_fin < v.date_debut) throw new Error('La date de fin précède la date de début.');

  if (cle === 'activites' && v.mouvement_uuid) {
    const m = await db.mouvements.get(v.mouvement_uuid);
    if (m?.secteur_uuid && v.secteur_uuid && m.secteur_uuid !== v.secteur_uuid) throw new Error('Ce mouvement appartient à un autre secteur.');
  }
}
