import { useApp } from '../App.jsx';
import { bilan, dettesParClient, produitsAvecStock } from '../actions.js';
import { useRequete } from '../crochets.js';
import { db } from '../db.js';
import { dateHeure, fcfa, jourLocal } from '../format.js';

export function Accueil() {
  const { naviguer } = useApp();
  const donnees = useRequete(async () => {
    const jour = jourLocal();
    const debutMois = jour.slice(0, 8) + '01';
    const jours = [];
    for (let i = 6; i >= 0; i--) {
      const d = new Date(); d.setDate(d.getDate() - i);
      const j = jourLocal(d);
      jours.push({ date: d, total: (await bilan(j, j)).chiffreAffaires });
    }
    const dettes = Object.values(await dettesParClient()).filter((d) => d > 0);
    return {
      jour: await bilan(jour, jour),
      mois: await bilan(debutMois, jour),
      jours,
      stockBas: (await produitsAvecStock()).filter((p) => p.stock <= p.seuil_alerte).sort((a, b) => a.stock - b.stock),
      dettes: { total: dettes.reduce((s, d) => s + d, 0), nb: dettes.length },
      dernieres: await db.ventes.orderBy('created_at').reverse().limit(6).toArray(),
    };
  }, [], null);
  if (!donnees) return null;
  const { jour, mois, jours, stockBas, dettes, dernieres } = donnees;
  const max = Math.max(...jours.map((j) => j.total), 1);

  return (
    <>
      <div class="entete">
        <h1>Tableau de bord</h1>
        <button class="btn btn-ambre" onClick={() => naviguer('caisse')}>Ouvrir la caisse</button>
      </div>
      <h2>Aujourd'hui</h2>
      <div class="grille">
        <div class="stat"><div class="lib">Ventes du jour</div><div class="val">{fcfa(jour.chiffreAffaires)}</div><div class="det">{jour.nbVentes} vente(s)</div></div>
        <div class="stat"><div class="lib">Argent encaissé</div><div class="val">{fcfa(jour.encaisse)}</div></div>
        <div class="stat"><div class="lib">Marge du jour</div><div class="val positif">{fcfa(jour.marge)}</div></div>
        <div class="stat"><div class="lib">Crédits accordés</div><div class="val negatif">{fcfa(jour.creditsAccordes)}</div></div>
      </div>
      <h2>Ce mois-ci</h2>
      <div class="grille">
        <div class="stat"><div class="lib">Chiffre d'affaires</div><div class="val">{fcfa(mois.chiffreAffaires)}</div></div>
        <div class="stat"><div class="lib">Marge brute</div><div class="val">{fcfa(mois.marge)}</div></div>
        <div class="stat"><div class="lib">Dépenses</div><div class="val">{fcfa(mois.depenses)}</div><div class="det">hors achats de marchandise</div></div>
        <div class="stat"><div class="lib">Bénéfice net</div><div class={'val ' + (mois.benefice >= 0 ? 'positif' : 'negatif')}>{fcfa(mois.benefice)}</div></div>
      </div>
      <div class="deux-col">
        <div class="carte">
          <h2>Ventes des 7 derniers jours</h2>
          <div style="display:flex; align-items:flex-end; gap:8px; height:140px">
            {jours.map((j, i) => (
              <div key={i} style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; height:100%; gap:4px" title={fcfa(j.total)}>
                <div class="aide" style="font-size:10px">{j.total ? Math.round(j.total / 1000) + 'k' : ''}</div>
                <div style={`width:100%; max-width:40px; min-height:2px; border-radius:4px 4px 0 0; height:${Math.round(j.total / max * 100)}%; background:${i === 6 ? 'var(--ambre-500)' : 'var(--vert-600)'}`}></div>
                <div class="aide" style="font-size:11px">{j.date.toLocaleDateString('fr-FR', { weekday: 'short' })}</div>
              </div>
            ))}
          </div>
        </div>
        <div class="carte">
          <h2>À surveiller</h2>
          <p style="margin-bottom:8px"><a onClick={() => naviguer('clients')}><b>{dettes.nb}</b> client(s) doivent <b>{fcfa(dettes.total)}</b></a></p>
          <p style="margin-bottom:6px"><b>{stockBas.length}</b> produit(s) en stock bas</p>
          <table><tbody>
            {stockBas.slice(0, 8).map((p) => (
              <tr key={p.uuid} class="clic" onClick={() => naviguer('produit', { uuid: p.uuid })}>
                <td>{p.nom}</td><td class="n"><span class={'badge ' + (p.stock <= 0 ? 'badge-rouge' : 'badge-ambre')}>{p.stock} {p.unite}</span></td>
              </tr>
            ))}
          </tbody></table>
        </div>
      </div>
      <div class="carte tableau">
        <h2>Dernières ventes</h2>
        <table><tbody>
          {dernieres.map((v) => (
            <tr key={v.uuid} class={'clic' + (v.statut === 'annulee' ? ' barre' : '')} onClick={() => naviguer('vente', { uuid: v.uuid })}>
              <td>{v.numero}</td><td>{dateHeure(v.created_at)}</td><td class="n">{fcfa(v.total)}</td>
            </tr>
          ))}
          {!dernieres.length && <tr><td class="vide">Pas encore de vente.</td></tr>}
        </tbody></table>
      </div>
    </>
  );
}
