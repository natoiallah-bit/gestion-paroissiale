import { useState } from 'preact/hooks';
import { useApp } from '../App.jsx';
import { useCartes, useRequete } from '../crochets.js';
import { db } from '../db.js';
import { fcfa } from '../format.js';
import { cumulsDepots, estLimite, intitule, peutEcrire, RESSOURCES, trier, valeur } from '../ressources.js';

const PAGE = 60;

export function Liste({ cle, filtres: filtresInitiaux = {} }) {
  const { session, naviguer } = useApp();
  const def = RESSOURCES[cle];
  const [q, setQ] = useState('');
  const [filtres, setFiltres] = useState(filtresInitiaux);
  const [du, setDu] = useState('');
  const [au, setAu] = useState('');
  const [max, setMax] = useState(PAGE);
  const cartes = useCartes();
  const toutes = useRequete(() => db.table(cle).toArray(), [cle], null);
  if (!toutes || !cartes) return null;

  const colonnes = def.champs.filter((c) => c.liste);
  // Colonnes calculées (cumuls des dépôts), jamais saisies
  const calculees = (def.calculees || []).filter((c) => !c.central || !estLimite(session.utilisateur));
  const cumuls = cle === 'depots' ? cumulsDepots(toutes) : null;
  const champDate = def.champs.find((c) => c.type === 'date' && c.liste);
  const filtrables = colonnes.filter((c) => c.type === 'choix' || (c.type === 'ref' && ['secteur_uuid', 'ceb_uuid', 'fidele_uuid'].includes(c.nom)));
  const mot = q.trim().toLowerCase();

  const lignes = trier(cle, toutes.filter((f) => {
    if (mot && !def.recherche.some((n) => String(f[n] ?? '').toLowerCase().includes(mot)) && !intitule(cle, f, cartes).toLowerCase().includes(mot)) return false;
    for (const [nom, v] of Object.entries(filtres)) if (v && f[nom] !== v) return false;
    if (champDate && du && f[champDate.nom] < du) return false;
    if (champDate && au && f[champDate.nom] > au) return false;
    return true;
  }), cartes);

  const totaux = cle === 'operations' ? {
    recettes: lignes.filter((o) => o.sens === 'recette').reduce((s, o) => s + o.montant, 0),
    depenses: lignes.filter((o) => o.sens === 'depense').reduce((s, o) => s + o.montant, 0),
  } : null;

  return (
    <>
      <div class="entete">
        <div><h1 style="margin:0">{def.libelle}</h1>{def.sous_titre && <div class="aide">{def.sous_titre}</div>}</div>
        {peutEcrire(session.utilisateur.role, cle) && (
          <button class="btn btn-ambre" onClick={() => naviguer('formulaire', { cle })}>+ Ajouter {def.article} {def.singulier}</button>
        )}
      </div>

      <div class="champs pas-imprimer" style="margin-bottom:12px; align-items:flex-end">
        <div style="flex:2; min-width:180px"><label>Recherche</label><input type="search" value={q} onInput={(e) => { setQ(e.target.value); setMax(PAGE); }} /></div>
        {filtrables.map((c) => {
          const options = c.type === 'choix' ? Object.entries(c.options) : [...cartes[c.ref].values()].filter((f) => c.ref !== 'cebs' || true).map((f) => [f.uuid, intitule(c.ref, f, cartes)]).sort((a, b) => a[1].localeCompare(b[1], 'fr'));
          if (c.type === 'ref' && options.length < 2) return null;
          return (
            <div key={c.nom}><label>{c.label}</label>
              <select value={filtres[c.nom] || ''} onChange={(e) => { setFiltres({ ...filtres, [c.nom]: e.target.value }); setMax(PAGE); }}>
                <option value="">Tous</option>
                {options.map(([k, lib]) => <option key={k} value={k}>{lib}</option>)}
              </select></div>
          );
        })}
        {champDate && <><div><label>Du</label><input type="date" value={du} onInput={(e) => setDu(e.target.value)} /></div><div><label>Au</label><input type="date" value={au} onInput={(e) => setAu(e.target.value)} /></div></>}
      </div>

      <div class="grille">
        <div class="stat"><div class="lib">Nombre</div><div class="val">{lignes.length}</div></div>
        {totaux && <>
          <div class="stat"><div class="lib">Recettes</div><div class="val positif">{fcfa(totaux.recettes)}</div></div>
          <div class="stat"><div class="lib">Dépenses</div><div class="val negatif">{fcfa(totaux.depenses)}</div></div>
          <div class="stat"><div class="lib">Solde</div><div class={'val ' + (totaux.recettes >= totaux.depenses ? 'positif' : 'negatif')}>{fcfa(totaux.recettes - totaux.depenses)}</div></div>
        </>}
        {cle === 'depots' && <div class="stat"><div class="lib">Total déposé</div><div class="val positif">{fcfa(lignes.reduce((s, d) => s + d.montant, 0))}</div></div>}
        {cle === 'mouvements' && <div class="stat"><div class="lib">Membres déclarés</div><div class="val">{lignes.reduce((s, m) => s + (m.nombre_membres || 0), 0)}</div></div>}
      </div>

      <div class="carte tableau">
        <table>
          <thead><tr>{colonnes.map((c) => <th key={c.nom} class={c.type === 'montant' ? 'n' : ''}>{c.label}</th>)}{calculees.map((c) => <th key={c.nom} class="n">{c.label}</th>)}</tr></thead>
          <tbody>
            {lignes.length === 0 && <tr><td colspan={colonnes.length + calculees.length} class="vide">Rien à afficher.</td></tr>}
            {lignes.slice(0, max).map((f) => (
              <tr key={f.uuid} class="clic" onClick={() => naviguer('fiche', { cle, uuid: f.uuid })}>
                {colonnes.map((c, i) => (
                  <td key={c.nom} class={c.type === 'montant' ? 'n' : ''}>
                    {i === 0 ? <b>{valeur(cle, f, c, cartes)}</b>
                      : c.nom === 'sens' ? <span class={'badge ' + (f.sens === 'recette' ? 'badge-vert' : 'badge-rouge')}>{valeur(cle, f, c, cartes)}</span>
                      : c.nom === 'statut' && f.statut !== 'actif' ? <span class="badge badge-gris">{valeur(cle, f, c, cartes)}</span>
                      : valeur(cle, f, c, cartes)}
                    {i === 0 && f._local && <> <span class="badge badge-ambre">à envoyer</span></>}
                  </td>
                ))}
                {calculees.map((c) => <td key={c.nom} class="n"><b>{fcfa(cumuls.get(f.uuid)?.[c.nom])}</b></td>)}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {lignes.length > max && <button class="btn btn-leger" onClick={() => setMax(max + PAGE)}>Afficher plus ({lignes.length - max} restants)</button>}
    </>
  );
}
