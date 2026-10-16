import { useApp } from '../App.jsx';
import { useRequete } from '../crochets.js';
import { fcfa } from '../format.js';
import { estLimite } from '../ressources.js';
import { comptes as calculerComptes } from '../statistiques.js';

// Compilation des comptes : CEB → secteur → vicariat
export function Comptes({ secteur = '' }) {
  const { session, naviguer } = useApp();
  const c = useRequete(() => calculerComptes(session, secteur || null), [secteur], null);
  if (!c) return null;

  return (
    <>
      <h1>Compilation des comptes</h1>
      <div class="grille">
        {c.central && <div class="stat"><div class="lib">Caisse centrale du vicariat</div><div class="val positif">{fcfa(c.central.caisse)}</div><div class="det">{fcfa(c.central.recu)} reçus des secteurs</div></div>}
        <div class="stat"><div class="lib">{estLimite(session.utilisateur) ? 'Compilé par le secteur' : 'Compilé dans le vicariat'}</div><div class="val">{fcfa(c.totalCompile)}</div></div>
        <div class="stat"><div class="lib">Déjà déposé au vicariat</div><div class="val positif">{fcfa(c.totalDepose)}</div></div>
        <div class="stat"><div class="lib">Reste à déposer</div><div class={'val ' + (c.totalReste > 0 ? 'negatif' : 'positif')}>{fcfa(c.totalReste)}</div></div>
      </div>
      {c.secteurs.map((l) => (
        <div key={l.secteur.uuid} class="carte tableau">
          <h2>Secteur {l.secteur.nom} <span class={'badge ' + (l.reste > 0 ? 'badge-ambre' : 'badge-vert')}>reste à déposer : {fcfa(l.reste)}</span></h2>
          <table>
            <thead><tr><th>Compte</th><th class="n">Dépôts / recettes</th><th class="n">Retraits / dépenses</th><th class="n">Solde</th></tr></thead>
            <tbody>
              {l.cebs.map((x) => (
                <tr key={x.ceb.uuid} class="clic" onClick={() => naviguer('fiche', { cle: 'cebs', uuid: x.ceb.uuid })}>
                  <td>{x.ceb.nom}</td><td class="n">{fcfa(x.recettes)}</td><td class="n">{fcfa(x.depenses)}</td><td class="n"><b>{fcfa(x.solde)}</b></td>
                </tr>
              ))}
              <tr><td>Opérations propres du secteur</td><td class="n">{fcfa(l.propres.recettes)}</td><td class="n">{fcfa(l.propres.depenses)}</td><td class="n"><b>{fcfa(l.propres.solde)}</b></td></tr>
              <tr><td><b>Compilation du secteur</b></td><td class="n">{fcfa(l.compile.recettes)}</td><td class="n">{fcfa(l.compile.depenses)}</td><td class="n"><b>{fcfa(l.compile.solde)}</b></td></tr>
              <tr><td>Déposé au vicariat ({l.nbDepots} dépôt{l.nbDepots > 1 ? 's' : ''})</td><td></td><td class="n">{fcfa(l.depose)}</td><td class="n"><b>{fcfa(l.reste)}</b> <span class="aide">à déposer</span></td></tr>
            </tbody>
          </table>
        </div>
      ))}
    </>
  );
}
