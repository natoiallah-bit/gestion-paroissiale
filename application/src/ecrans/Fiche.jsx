import { useApp } from '../App.jsx';
import { useCartes, useRequete } from '../crochets.js';
import { db } from '../db.js';
import { cumulsDepots, estLimite, intitule, peutEcrire, peutLire, RESSOURCES, trier, valeur } from '../ressources.js';
import { fcfa } from '../format.js';

export function Fiche({ cle, uuid }) {
  const { session, naviguer } = useApp();
  const def = RESSOURCES[cle];
  const user = session.utilisateur;
  const cartes = useCartes();
  const donnees = useRequete(async () => {
    const fiche = await db.table(cle).get(uuid);
    const liees = [];
    for (const l of def.liees || []) {
      if (!peutLire(user.role, l.ressource)) continue;
      liees.push({ ...l, fiches: await db.table(l.ressource).where(l.cle).equals(uuid).toArray() });
    }
    // Cumuls recalculés à chaque dépôt ; solde du compte d'une CEB
    const cumul = cle === 'depots' ? cumulsDepots(await db.depots.toArray()).get(uuid) : null;
    let solde = null;
    if (cle === 'cebs' && peutLire(user.role, 'operations')) {
      const ops = await db.operations.where('ceb_uuid').equals(uuid).toArray();
      solde = ops.reduce((s, o) => s + (o.sens === 'recette' ? o.montant : -o.montant), 0);
    }
    return { fiche, liees, cumul, solde };
  }, [cle, uuid], null);
  if (!donnees || !cartes) return null;
  const { fiche, liees, cumul, solde } = donnees;
  if (!fiche) return <div class="carte vide">Fiche introuvable (peut-être supprimée sur le serveur).</div>;

  const modifiable = peutEcrire(user.role, cle) && !(estLimite(user) && (def.portee === 'aucune' || (fiche.secteur_uuid ?? null) !== user.secteur_uuid));
  const titre = intitule(cle, fiche, cartes);

  return (
    <>
      <div class="entete">
        <h1>{titre}</h1>
        <div class="actions">
          {cle === 'sacrements' && <button class="btn" onClick={() => naviguer('extrait', { uuid })}>Extrait</button>}
          {cle === 'fideles' && peutEcrire(user.role, 'sacrements') && <button class="btn btn-ambre" onClick={() => naviguer('formulaire', { cle: 'sacrements', valeurs: { fidele_uuid: uuid } })}>+ Sacrement</button>}
          {modifiable && <button class="btn btn-leger" onClick={() => naviguer('formulaire', { cle, uuid })}>Modifier</button>}
        </div>
      </div>
      {fiche._local && <div class="alerte alerte-info">Cette fiche sera envoyée au serveur à la prochaine synchronisation.</div>}

      <div class="carte tableau">
        <table>
          <tbody>
            {def.champs.map((c) => (
              <tr key={c.nom}>
                <th style="width:200px; vertical-align:top">{c.label}</th>
                <td style="white-space:pre-line">
                  {c.type === 'ref' && fiche[c.nom] && peutLire(user.role, c.ref) && cartes[c.ref]?.get(fiche[c.nom])
                    ? <a onClick={() => naviguer('fiche', { cle: c.ref, uuid: fiche[c.nom] })}>{valeur(cle, fiche, c, cartes, false)}</a>
                    : valeur(cle, fiche, c, cartes, false)}
                </td>
              </tr>
            ))}
            {(def.calculees || []).filter((c) => cumul && (!c.central || !estLimite(user))).map((c) => (
              <tr key={c.nom}><th>{c.label}</th><td><b>{fcfa(cumul[c.nom])}</b> <span class="aide">après ce dépôt</span></td></tr>
            ))}
            {solde !== null && <tr><th>Solde du compte</th><td><b>{fcfa(solde)}</b></td></tr>}
          </tbody>
        </table>
      </div>

      {liees.map((l) => {
        const defL = RESSOURCES[l.ressource];
        const colonnes = defL.champs.filter((c) => c.liste && c.nom !== l.cle);
        return (
          <div key={l.ressource} class="carte tableau">
            <h2>{l.titre} ({l.fiches.length})</h2>
            <table>
              <thead><tr>{colonnes.map((c) => <th key={c.nom}>{c.label}</th>)}</tr></thead>
              <tbody>
                {l.fiches.length === 0 && <tr><td colspan={colonnes.length} class="vide">Aucun.</td></tr>}
                {trier(l.ressource, l.fiches, cartes).map((f) => (
                  <tr key={f.uuid} class="clic" onClick={() => naviguer('fiche', { cle: l.ressource, uuid: f.uuid })}>
                    {colonnes.map((c) => <td key={c.nom}>{valeur(l.ressource, f, c, cartes)}</td>)}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        );
      })}
      <button class="btn btn-leger" onClick={() => naviguer('liste', { cle })}>← Retour à la liste</button>
    </>
  );
}
