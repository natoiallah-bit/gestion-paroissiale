import { db } from './db.js';
import { jourLocal } from './format.js';
import { peutLire, SACREMENTS } from './ressources.js';

// Mêmes chiffres que le tableau de bord du site, calculés à partir des données de l'appareil.
// Le serveur n'envoie à chaque compte que ce qu'il a le droit de voir : rien à filtrer ici,
// sauf le choix d'une paroisse par l'administrateur.

const dans = (paroisse) => (f) => !paroisse || f.paroisse_uuid === paroisse;

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

export async function tableauDeBord(session, paroisse = null) {
  const role = session.utilisateur.role;
  const filtre = dans(paroisse);
  const aujourdhui = jourLocal();
  const annee = aujourdhui.slice(0, 4);

  const [fideles, cebs, mouvements, sacrements, activites, annonces, operations, paroisses] = await Promise.all([
    db.fideles.toArray(), db.cebs.toArray(), db.mouvements.toArray(), db.sacrements.toArray(),
    db.activites.toArray(), db.annonces.toArray(), db.operations.toArray(), db.paroisses.toArray(),
  ]);
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

  let parParoisse = null;
  if (role === 'admin' && !paroisse) {
    parParoisse = paroisses.map((p) => ({
      paroisse: p,
      fideles: fideles.filter((f) => f.paroisse_uuid === p.uuid && f.statut === 'actif').length,
      cebs: cebs.filter((c) => c.paroisse_uuid === p.uuid).length,
      mouvements: mouvements.filter((m) => m.paroisse_uuid === p.uuid).length,
      sacrements: sacrements.filter((s) => s.paroisse_uuid === p.uuid && s.date_sacrement.startsWith(annee)).length,
      recettes: operations.filter((o) => o.paroisse_uuid === p.uuid && o.sens === 'recette' && o.date_operation.startsWith(annee)).reduce((s, o) => s + o.montant, 0),
    })).sort((a, b) => a.paroisse.nom.localeCompare(b.paroisse.nom, 'fr'));
  }

  return { resume, parType, prochaines, enCours, finances, parParoisse, annee, sacrementsLibelles: SACREMENTS };
}

export { bilan };
