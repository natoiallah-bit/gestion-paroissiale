// Description des fiches (fidèles, CEB, sacrements…) : fichier ressources.json produit par le serveur
// (php artisan vicariat:ressources), donc identique à celle du site et des règles de synchronisation.
import donnees from './ressources.json';
import { fcfa } from './format.js';

export const RESSOURCES = donnees.ressources;
export const CLES = Object.keys(RESSOURCES);
export const SACREMENTS = donnees.sacrements;
export const CATEGORIES_RECETTES = donnees.categories_recettes;
export const CATEGORIES_DEPENSES = donnees.categories_depenses;

export const peutLire = (role, cle) => RESSOURCES[cle].lecture.includes(role);
export const peutEcrire = (role, cle) => RESSOURCES[cle].ecriture.includes(role);

export const champ = (cle, nom) => RESSOURCES[cle].champs.find((c) => c.nom === nom);

// Un compte rattaché à un secteur ne travaille que dans ce secteur (l'administrateur voit tout)
export const estLimite = (utilisateur) => !!utilisateur?.secteur_uuid && utilisateur.role !== 'admin';

// Cumuls recalculés à chaque dépôt (mêmes règles que le serveur : dépôts classés par date puis par identifiant)
export function cumulsDepots(depots) {
  const parSecteur = {};
  let general = 0;
  const cumuls = new Map();
  for (const d of [...depots].sort((a, b) => a.date_depot.localeCompare(b.date_depot) || (a.uuid < b.uuid ? -1 : 1))) {
    parSecteur[d.secteur_uuid] = (parSecteur[d.secteur_uuid] || 0) + d.montant;
    general += d.montant;
    cumuls.set(d.uuid, { cumul_secteur: parSecteur[d.secteur_uuid], total_vicariat: general });
  }
  return cumuls;
}

export function intitule(cle, f, cartes = {}) {
  if (!f) return '—';
  if (cle === 'fideles') return `${f.prenoms} ${String(f.nom).toUpperCase()}`.trim();
  if (cle === 'sacrements') return `${SACREMENTS[f.type] || f.type} — ${intitule('fideles', cartes.fideles?.get(f.fidele_uuid))}`;
  return f.nom || f.titre || f.libelle || '—';
}

const jour = (s) => (s ? String(s).slice(0, 10).split('-').reverse().join('/') : '');

/** Valeur d'un champ prête à afficher (cartes = tables de référence chargées en mémoire) */
export function valeur(cle, f, c, cartes = {}, court = true) {
  const v = f[c.nom];
  if (v === null || v === undefined || v === '') return '—';
  switch (c.type) {
    case 'ref': return intitule(c.ref, cartes[c.ref]?.get(v), cartes);
    case 'choix': return c.options[v] ?? String(v);
    case 'date': return jour(v);
    case 'montant': return fcfa(v);
    case 'long': return court && String(v).length > 80 ? String(v).slice(0, 79) + '…' : String(v);
    default: return String(v);
  }
}

export function trier(cle, fiches, cartes = {}) {
  const tri = RESSOURCES[cle].tri;
  const desc = tri.startsWith('-');
  const col = tri.replace(/^-/, '');
  const copie = [...fiches].sort((a, b) => String(a[col] ?? '').localeCompare(String(b[col] ?? ''), 'fr', { sensitivity: 'base' }));
  return desc ? copie.reverse() : copie;
}
