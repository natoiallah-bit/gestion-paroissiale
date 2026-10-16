import { useState } from 'preact/hooks';
import { useApp } from '../App.jsx';
import { useEtatSync, useRequete } from '../crochets.js';
import { db } from '../db.js';
import { dateHeure, ilYa } from '../format.js';
import { seDeconnecter } from '../session.js';
import { synchroniser } from '../sync.js';

const LIBELLES = { ventes: 'Vente', clients: 'Client', remboursements: 'Remboursement', produits: 'Produit', mouvements: 'Mouvement de stock', annulations: 'Annulation', depenses: 'Dépense', categories: 'Catégorie', fournisseurs: 'Fournisseur' };

export function Synchro() {
  const { session, recharger } = useApp();
  const etat = useEtatSync();
  const [erreur, setErreur] = useState(null);
  const donnees = useRequete(async () => ({
    envois: await db.envois.orderBy('id').toArray(),
    rejets: await db.rejets.orderBy('id').reverse().toArray(),
    derniere: (await db.reglages.get('derniere_synchro'))?.valeur,
  }), [], null);
  if (!donnees) return null;

  const lancer = async () => {
    setErreur(null);
    try { await synchroniser(); } catch (e) { setErreur(e.message); }
  };

  const deconnecter = async () => {
    if (donnees.envois.length && !confirm(`${donnees.envois.length} opération(s) ne sont pas encore envoyées. Elles resteront sur cet appareil et partiront à la prochaine connexion au même serveur. Se déconnecter ?`)) return;
    await seDeconnecter();
    await recharger();
  };

  const parType = donnees.envois.reduce((acc, e) => ({ ...acc, [e.table]: (acc[e.table] || 0) + 1 }), {});

  return (
    <>
      <h1>Synchronisation</h1>
      {(erreur || etat.erreur) && <div class="alerte alerte-erreur">{erreur || etat.erreur}</div>}
      {etat.sessionExpiree && <div class="alerte alerte-erreur">Votre session a expiré : déconnectez-vous puis reconnectez-vous. Les opérations en attente seront conservées.</div>}
      {etat.horsLigne && <div class="alerte alerte-info">Pas de connexion au serveur. Vous pouvez continuer à travailler : tout sera envoyé dès que la connexion reviendra.</div>}

      <div class="grille">
        <div class="stat"><div class="lib">Dernière synchronisation</div><div class="val" style="font-size:16px">{ilYa(donnees.derniere)}</div></div>
        <div class="stat"><div class="lib">En attente d'envoi</div><div class={'val ' + (donnees.envois.length ? 'negatif' : 'positif')}>{donnees.envois.length}</div>
          <div class="det">{Object.entries(parType).map(([t, n]) => `${n} ${LIBELLES[t] || t}`).join(' · ')}</div></div>
      </div>
      <button class="btn btn-ambre" onClick={lancer} disabled={etat.enCours}>{etat.enCours ? 'Synchronisation…' : 'Synchroniser maintenant'}</button>

      {donnees.rejets.length > 0 && (
        <div class="carte tableau" style="margin-top:16px">
          <h2>Opérations refusées par le serveur</h2>
          <table>
            <thead><tr><th>Date</th><th>Type</th><th>Raison</th></tr></thead>
            <tbody>{donnees.rejets.map((r) => <tr key={r.id}><td>{dateHeure(r.date)}</td><td>{LIBELLES[r.table] || r.table}</td><td>{r.raison}</td></tr>)}</tbody>
          </table>
          <button class="btn btn-leger btn-petit" style="margin-top:8px" onClick={() => db.rejets.clear()}>Effacer la liste</button>
        </div>
      )}

      <div class="carte" style="margin-top:16px">
        <h2>Cet appareil</h2>
        <p>Serveur : <b>{session.serveur}</b></p>
        <p>Connecté : <b>{session.utilisateur.nom}</b> ({session.utilisateur.role === 'gerant' ? 'Gérant' : 'Vendeur'})</p>
        <p>Appareil : <b>{session.appareil.nom}</b> — code <b>{session.appareil.code}</b> (préfixe des tickets)</p>
        <button class="btn btn-leger" style="margin-top:10px" onClick={deconnecter}>Se déconnecter</button>
      </div>
    </>
  );
}
