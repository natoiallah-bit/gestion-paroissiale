import { useState } from 'preact/hooks';
import { useApp } from '../App.jsx';
import { enregistrerFiche } from '../actions.js';
import { useCartes, useRequete } from '../crochets.js';
import { db } from '../db.js';
import { jourLocal } from '../format.js';
import { estLimite, intitule, RESSOURCES } from '../ressources.js';

export function Formulaire({ cle, uuid = null, valeurs: initiales = {} }) {
  const { session, naviguer } = useApp();
  const def = RESSOURCES[cle];
  const user = session.utilisateur;
  const cartes = useCartes();
  const existant = useRequete(() => (uuid ? db.table(cle).get(uuid) : Promise.resolve(null)), [cle, uuid], undefined);
  const [v, setV] = useState(null);
  const [erreur, setErreur] = useState(null);
  const [filtres, setFiltres] = useState({});
  if (existant === undefined || !cartes) return null;

  // Valeurs de départ (une seule fois)
  const courant = v ?? (() => {
    const base = {};
    for (const c of def.champs) base[c.nom] = existant?.[c.nom] ?? initiales[c.nom] ?? c.defaut ?? '';
    if (!existant && cle === 'operations') base.date_operation = jourLocal();
    if (!existant && cle === 'sacrements') base.date_sacrement = jourLocal();
    if (!existant && cle === 'annonces') base.date_debut = jourLocal();
    if (!existant && cle === 'activites') base.date_activite = jourLocal();
    return base;
  })();
  const maj = (nom, valeur) => setV({ ...courant, [nom]: valeur });

  const options = (c) => {
    let liste = [...cartes[c.ref].values()];
    // CEB : seulement celles du secteur choisie
    if (c.ref === 'cebs' && courant.secteur_uuid) liste = liste.filter((x) => x.secteur_uuid === courant.secteur_uuid);
    if (c.ref === 'mouvements' && courant.secteur_uuid) liste = liste.filter((x) => !x.secteur_uuid || x.secteur_uuid === courant.secteur_uuid);
    const mot = (filtres[c.nom] || '').toLowerCase();
    return liste.map((x) => ({ uuid: x.uuid, texte: intitule(c.ref, x, cartes) })).filter((o) => !mot || o.texte.toLowerCase().includes(mot) || o.uuid === courant[c.nom])
      .sort((a, b) => a.texte.localeCompare(b.texte, 'fr'));
  };

  const valider = async (e) => {
    e.preventDefault();
    setErreur(null);
    try {
      const fiche = await enregistrerFiche(cle, courant, session, existant);
      naviguer('fiche', { cle, uuid: fiche.uuid }, existant ? 'Modifications enregistrées.' : 'Enregistré.');
    } catch (err) {
      setErreur(err.message);
      window.scrollTo(0, 0);
    }
  };

  return (
    <>
      <h1>{existant ? `Modifier « ${intitule(cle, existant, cartes)} »` : `${def.singulier[0].toUpperCase()}${def.singulier.slice(1)} — nouveau`}</h1>
      <form class="carte formulaire" onSubmit={valider}>
        {erreur && <div class="alerte alerte-erreur">{erreur}</div>}
        {def.champs.map((c) => {
          if (c.nom === 'secteur_uuid' && estLimite(user)) return null;
          const id = 'champ-' + c.nom;
          const valeur = courant[c.nom] ?? '';
          const requis = !!c.requis;
          return (
            <div key={c.nom}>
              <label for={id}>{c.label}{requis ? ' *' : ''}</label>
              {c.type === 'long' && <textarea id={id} rows="4" maxLength={c.max || 2000} value={valeur} required={requis} onInput={(e) => maj(c.nom, e.target.value)} />}
              {c.type === 'choix' && (
                <select id={id} value={valeur} required={requis} onChange={(e) => maj(c.nom, e.target.value)}>
                  <option value="">{requis ? 'Choisir…' : '—'}</option>
                  {Object.entries(c.options).map(([k, lib]) => <option key={k} value={k}>{lib}</option>)}
                </select>
              )}
              {c.type === 'ref' && (() => {
                const liste = options(c);
                return (
                  <>
                    {cartes[c.ref].size > 25 && <input type="search" placeholder="Rechercher dans la liste…" style="margin-bottom:6px" value={filtres[c.nom] || ''} onInput={(e) => setFiltres({ ...filtres, [c.nom]: e.target.value })} />}
                    <select id={id} value={valeur} required={requis} onChange={(e) => maj(c.nom, e.target.value)}>
                      <option value="">{requis ? 'Choisir…' : c.nom === 'secteur_uuid' ? 'Tout le vicariat' : '—'}</option>
                      {liste.map((o) => <option key={o.uuid} value={o.uuid}>{o.texte}</option>)}
                    </select>
                  </>
                );
              })()}
              {c.type === 'date' && <input id={id} type="date" value={valeur} required={requis} onInput={(e) => maj(c.nom, e.target.value)} />}
              {c.type === 'heure' && <input id={id} type="time" value={valeur} onInput={(e) => maj(c.nom, e.target.value)} />}
              {(c.type === 'entier' || c.type === 'montant') && <input id={id} type="number" inputMode="numeric" min={c.type === 'montant' ? 1 : 0} step="1" value={valeur} required={requis} onInput={(e) => maj(c.nom, e.target.value)} />}
              {(c.type === 'texte' || c.type === 'tel') && <input id={id} type={c.type === 'tel' ? 'tel' : 'text'} maxLength={c.max || 255} value={valeur} required={requis} onInput={(e) => maj(c.nom, e.target.value)} />}
              {c.aide && <div class="aide">{c.aide}</div>}
            </div>
          );
        })}
        <div class="actions" style="margin-top:16px">
          <button class="btn btn-ambre">Enregistrer</button>
          <button type="button" class="btn btn-leger" onClick={() => (existant ? naviguer('fiche', { cle, uuid }) : naviguer('liste', { cle }))}>Annuler</button>
        </div>
      </form>
    </>
  );
}
