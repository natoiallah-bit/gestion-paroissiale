import { db } from './db.js';
import { jourLocal } from './format.js';
import { estLimite, peutLire, SACREMENTS } from './ressources.js';

// Mêmes chiffres que le tableau de bord du site, calculés à partir des données de l'appareil.
// Le serveur n'envoie à chaque compte que ce qu'il a le droit de voir : rien à filtrer ici,
// sauf le choix d'un secteur par l'administrateur.

const dans = (secteur) => (f) => !secteur || f.secteur_uuid === secteur;

function bilan(operations, debut, fin) {
  const dansPeriode = operations.filter((o) => o.date_operation >= debut && o.date_operation <= fin);
  const total = (sens) => dansPeriode.filter((o) => o.sens === sens).reduce((s, o) => s + o.montant, 0);
  const recettes = total('recette');
  const depenses = total('depense');
  const parCategorie = new Map();
  for (const o of dansPeriode) {
    const cle = o.sens + '|' + o.categorie;
    const l = parCategorie.get(cle) || { sens: o.sens, categorie: o.categorie, total: 0, n: 0 };
    l.total += o.montant;
    l.n += 1;
    parCategorie.set(cle, l);
  }
  return { recettes, depenses, solde: recettes - depenses, parCategorie: [...parCategorie.values()].sort((a, b) => b.total - a.total) };
}

export async function tableauDeBord(session, secteur = null) {
  const role = session.utilisateur.role;
  const filtre = dans(secteur);
  const aujourdhui = jourLocal();
  const annee = aujourdhui.slice(0, 4);

  const [fideles, cebs, mouvements, sacrements, activites, annonces, operations, secteurs] = await Promise.all([
    db.fideles.toArray(), db.cebs.toArray(), db.mouvements.toArray(), db.sacrements.toArray(),
    db.activites.toArray(), db.annonces.toArray(), db.operations.toArray(), db.secteurs.toArray(),
  ]);
  const depots = await db.depots.toArray();
  const actifs = fideles.filter(filtre).filter((f) => f.statut === 'actif');
  const sacrementsAnnee = sacrements.filter(filtre).filter((s) => s.date_sacrement.startsWith(annee));
  const parType = {};
  for (const s of sacrementsAnnee) parType[s.type] = (parType[s.type] || 0) + 1;

  const resume = {
    fideles: actifs.length,
    hommes: actifs.filter((f) => f.sexe === 'M').length,
    femmes: actifs.filter((f) => f.sexe === 'F').length,
    familles: new Set(actifs.map((f) => f.famille).filter(Boolean)).size,
    cebs: cebs.filter(filtre).length,
    mouvements: mouvements.filter(filtre).length,
    membresMouvements: mouvements.filter(filtre).reduce((s, m) => s + (m.nombre_membres || 0), 0),
    sacrementsAnnee: sacrementsAnnee.length,
  };

  const prochaines = activites.filter(filtre).filter((a) => a.date_activite >= aujourdhui)
    .sort((a, b) => (a.date_activite + (a.heure || '')).localeCompare(b.date_activite + (b.heure || ''))).slice(0, 8);
  const enCours = annonces.filter(filtre).filter((a) => a.date_debut <= aujourdhui && (!a.date_fin || a.date_fin >= aujourdhui))
    .sort((a, b) => b.date_debut.localeCompare(a.date_debut)).slice(0, 5);

  let finances = null;
  if (peutLire(role, 'operations')) {
    const ops = operations.filter(filtre);
    finances = { mois: bilan(ops, annee + aujourdhui.slice(4, 7) + '-01', aujourdhui), annee: bilan(ops, annee + '-01-01', aujourdhui) };
  }

  let parSecteur = null;
  if (role === 'admin' && !secteur) {
    parSecteur = secteurs.map((p) => ({
      secteur: p,
      fideles: fideles.filter((f) => f.secteur_uuid === p.uuid && f.statut === 'actif').length,
      cebs: cebs.filter((c) => c.secteur_uuid === p.uuid).length,
      mouvements: mouvements.filter((m) => m.secteur_uuid === p.uuid).length,
      sacrements: sacrements.filter((s) => s.secteur_uuid === p.uuid && s.date_sacrement.startsWith(annee)).length,
      recettes: operations.filter((o) => o.secteur_uuid === p.uuid && o.sens === 'recette' && o.date_operation.startsWith(annee)).reduce((s, o) => s + o.montant, 0),
      depose: depots.filter((d) => d.secteur_uuid === p.uuid).reduce((s, d) => s + d.montant, 0),
    })).sort((a, b) => a.secteur.nom.localeCompare(b.secteur.nom, 'fr'));
  }

  return { resume, parType, prochaines, enCours, finances, parSecteur, annee, sacrementsLibelles: SACREMENTS };
}

/**
 * Compilation des comptes, du bas vers le haut (même calcul que le serveur) :
 * compte de chaque CEB → compilation du secteur (− ce qui est déjà déposé au vicariat) → vicariat.
 */
export async function comptes(session, secteur = null) {
  const [secteurs, cebs, operations, depots] = await Promise.all([db.secteurs.toArray(), db.cebs.toArray(), db.operations.toArray(), db.depots.toArray()]);
  const somme = (liste, sens) => liste.filter((o) => o.sens === sens).reduce((s, o) => s + o.montant, 0);
  const solde = (liste) => { const recettes = somme(liste, 'recette'); const depenses = somme(liste, 'depense'); return { recettes, depenses, solde: recettes - depenses }; };

  const lignes = secteurs.filter((s) => !secteur || s.uuid === secteur).sort((a, b) => a.nom.localeCompare(b.nom, 'fr')).map((s) => {
    const ops = operations.filter((o) => o.secteur_uuid === s.uuid);
    const deposes = depots.filter((d) => d.secteur_uuid === s.uuid);
    const compile = solde(ops);
    const depose = deposes.reduce((t, d) => t + d.montant, 0);
    return {
      secteur: s,
      cebs: cebs.filter((c) => c.secteur_uuid === s.uuid).sort((a, b) => a.nom.localeCompare(b.nom, 'fr')).map((c) => ({ ceb: c, ...solde(ops.filter((o) => o.ceb_uuid === c.uuid)) })),
      propres: solde(ops.filter((o) => !o.ceb_uuid)),
      compile, depose, nbDepots: deposes.length, reste: compile.solde - depose,
    };
  });

  let central = null;
  if (!estLimite(session.utilisateur) && !secteur) {
    const propres = solde(operations.filter((o) => !o.secteur_uuid));
    const recu = depots.reduce((t, d) => t + d.montant, 0);
    central = { recu, propres, caisse: recu + propres.solde };
  }
  return {
    secteurs: lignes, central,
    totalCompile: lignes.reduce((t, l) => t + l.compile.solde, 0) + (central?.propres.solde || 0),
    totalDepose: lignes.reduce((t, l) => t + l.depose, 0),
    totalReste: lignes.reduce((t, l) => t + l.reste, 0),
  };
}

export { bilan };
