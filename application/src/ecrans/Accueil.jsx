import { useState } from 'preact/hooks';
import { useApp } from '../App.jsx';
import { useRequete } from '../crochets.js';
import { db } from '../db.js';
import { fcfa, nombre } from '../format.js';
import { estLimite, peutLire } from '../ressources.js';
import { comptes as calculerComptes, tableauDeBord } from '../statistiques.js';

const jourCourt = (iso) => new Date(iso + 'T12:00:00').toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit', month: '2-digit' });

export function Accueil() {
  const { session, naviguer } = useApp();
  const [secteur, setSecteur] = useState('');
  const admin = session.utilisateur.role === 'admin';
  const secteurs = useRequete(() => db.secteurs.orderBy('nom').toArray(), [], []);
  const d = useRequete(() => tableauDeBord(session, secteur || null), [secteur], null);
  const comptes = useRequete(() => (peutLire(session.utilisateur.role, 'operations') ? calculerComptes(session, secteur || null) : Promise.resolve(null)), [secteur], null);
  if (!d) return null;
  const r = d.resume;
  const lecture = (cle) => peutLire(session.utilisateur.role, cle);

  return (
    <>
      <div class="entete">
        <h1>Tableau de bord{secteur ? ' — ' + secteurs.find((p) => p.uuid === secteur)?.nom : ''}</h1>
        {admin && secteurs.length > 1 && (
          <select style="width:auto" value={secteur} onChange={(e) => setSecteur(e.target.value)}>
            <option value="">Tout le vicariat</option>
            {secteurs.map((p) => <option key={p.uuid} value={p.uuid}>{p.nom}</option>)}
          </select>
        )}
      </div>

      {lecture('fideles') && (
        <div class="grille">
          <div class="stat"><div class="lib">Fidèles (actifs)</div><div class="val">{nombre(r.fideles)}</div><div class="det">{r.hommes} hommes · {r.femmes} femmes</div></div>
          <div class="stat"><div class="lib">Familles</div><div class="val">{nombre(r.familles)}</div></div>
          <div class="stat"><div class="lib">CEB</div><div class="val">{r.cebs}</div></div>
          <div class="stat"><div class="lib">Mouvements & pastorales</div><div class="val">{r.mouvements}</div><div class="det">{nombre(r.membresMouvements)} membres déclarés</div></div>
          <div class="stat"><div class="lib">Sacrements en {d.annee}</div><div class="val">{r.sacrementsAnnee}</div>
            <div class="det">{Object.entries(d.parType).map(([t, n]) => `${d.sacrementsLibelles[t]} ${n}`).join(' · ')}</div></div>
        </div>
      )}

      {d.finances && (
        <>
          <h2>Finances</h2>
          <div class="grille">
            <div class="stat"><div class="lib">Recettes du mois</div><div class="val positif">{fcfa(d.finances.mois.recettes)}</div></div>
            <div class="stat"><div class="lib">Dépenses du mois</div><div class="val negatif">{fcfa(d.finances.mois.depenses)}</div></div>
            <div class="stat"><div class="lib">Solde du mois</div><div class={'val ' + (d.finances.mois.solde >= 0 ? 'positif' : 'negatif')}>{fcfa(d.finances.mois.solde)}</div></div>
            <div class="stat"><div class="lib">Solde de l'année</div><div class={'val ' + (d.finances.annee.solde >= 0 ? 'positif' : 'negatif')}>{fcfa(d.finances.annee.solde)}</div><div class="det">recettes {fcfa(d.finances.annee.recettes)}</div></div>
          </div>
        </>
      )}

      {comptes && (
        <>
          <div class="entete"><h2 style="margin:0">Comptes</h2><button class="btn btn-leger btn-petit" onClick={() => naviguer('comptes', { secteur })}>Détail par CEB</button></div>
          <div class="grille">
            {comptes.central && <div class="stat"><div class="lib">Caisse centrale du vicariat</div><div class="val positif">{fcfa(comptes.central.caisse)}</div><div class="det">{fcfa(comptes.central.recu)} reçus des secteurs</div></div>}
            <div class="stat"><div class="lib">{estLimite(session.utilisateur) ? 'Compilé par le secteur' : 'Compilé dans le vicariat'}</div><div class="val">{fcfa(comptes.totalCompile)}</div></div>
            <div class="stat"><div class="lib">Déjà déposé au vicariat</div><div class="val positif">{fcfa(comptes.totalDepose)}</div></div>
            <div class="stat"><div class="lib">Reste à déposer</div><div class={'val ' + (comptes.totalReste > 0 ? 'negatif' : 'positif')}>{fcfa(comptes.totalReste)}</div></div>
          </div>
        </>
      )}

      <div class="deux-col">
        <div class="carte">
          <h2>Annonces en cours</h2>
          {d.enCours.length === 0 && <p class="vide">Aucune annonce.</p>}
          {d.enCours.map((a) => (
            <p key={a.uuid} style="margin-bottom:10px">
              <a onClick={() => naviguer('fiche', { cle: 'annonces', uuid: a.uuid })}><b>{a.titre}</b></a>
              {!a.secteur_uuid && <> <span class="badge badge-ambre">vicariat</span></>}<br />
              <span class="aide">{a.contenu.length > 120 ? a.contenu.slice(0, 119) + '…' : a.contenu}</span>
            </p>
          ))}
        </div>
        <div class="carte">
          <h2>Prochaines activités</h2>
          {d.prochaines.length === 0 && <p class="vide">Rien de prévu.</p>}
          {d.prochaines.map((a) => (
            <p key={a.uuid} style="margin-bottom:10px">
              <b>{jourCourt(a.date_activite)}{a.heure ? ' à ' + a.heure : ''}</b> — <a onClick={() => naviguer('fiche', { cle: 'activites', uuid: a.uuid })}>{a.titre}</a>
              {a.lieu && <span class="aide"> · {a.lieu}</span>}
              {!a.secteur_uuid && <> <span class="badge badge-ambre">vicariat</span></>}
            </p>
          ))}
        </div>
      </div>

      {d.parSecteur && (
        <div class="carte tableau">
          <h2>Les secteurs du vicariat</h2>
          <table>
            <thead><tr><th>Secteur</th><th class="n">Fidèles</th><th class="n">CEB</th><th class="n">Mouvements</th><th class="n">Sacrements {d.annee}</th><th class="n">Recettes {d.annee}</th><th class="n">Déposé au vicariat</th></tr></thead>
            <tbody>
              {d.parSecteur.length === 0 && <tr><td colspan="7" class="vide">Aucun secteur.</td></tr>}
              {d.parSecteur.map((l) => (
                <tr key={l.secteur.uuid} class="clic" onClick={() => setSecteur(l.secteur.uuid)}>
                  <td><b>{l.secteur.nom}</b></td><td class="n">{nombre(l.fideles)}</td><td class="n">{l.cebs}</td><td class="n">{l.mouvements}</td>
                  <td class="n">{l.sacrements}</td><td class="n">{fcfa(l.recettes)}</td><td class="n">{fcfa(l.depose)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  );
}
