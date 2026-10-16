import { useState } from 'preact/hooks';
import { useApp } from '../App.jsx';
import { detteClient, dettesParClient, enregistrerClient, rembourser } from '../actions.js';
import { useRequete } from '../crochets.js';
import { db } from '../db.js';
import { dateHeure, fcfa, MODES } from '../format.js';

export function Clients() {
  const { naviguer } = useApp();
  const [q, setQ] = useState('');
  const [debiteurs, setDebiteurs] = useState(false);
  const donnees = useRequete(async () => ({ clients: await db.clients.orderBy('nom').toArray(), dettes: await dettesParClient() }), [], null);
  if (!donnees) return null;

  const texte = q.trim().toLowerCase();
  const liste = donnees.clients
    .filter((c) => !texte || (c.nom + ' ' + (c.telephone || '')).toLowerCase().includes(texte))
    .filter((c) => !debiteurs || (donnees.dettes[c.uuid] || 0) > 0);
  const totalDettes = Object.values(donnees.dettes).filter((d) => d > 0).reduce((s, d) => s + d, 0);

  return (
    <>
      <div class="entete">
        <h1>Clients & crédits</h1>
        <button class="btn btn-ambre" onClick={() => naviguer('clientForm')}>+ Nouveau client</button>
      </div>
      <div class="grille"><div class="stat"><div class="lib">Total des dettes clients</div><div class="val negatif">{fcfa(totalDettes)}</div></div></div>
      <div class="champs" style="margin-bottom:12px; align-items:center">
        <div style="flex:2"><input type="search" placeholder="Nom ou téléphone" value={q} onInput={(e) => setQ(e.target.value)} /></div>
        <label class="case" style="margin:0"><input type="checkbox" checked={debiteurs} onChange={(e) => setDebiteurs(e.target.checked)} /> Seulement ceux qui doivent</label>
      </div>
      <div class="carte tableau">
        <table>
          <thead><tr><th>Client</th><th>Téléphone</th><th class="n">Doit</th></tr></thead>
          <tbody>
            {liste.map((c) => {
              const dette = donnees.dettes[c.uuid] || 0;
              return (
                <tr key={c.uuid} class="clic" onClick={() => naviguer('client', { uuid: c.uuid })}>
                  <td><b>{c.nom}</b></td><td>{c.telephone || '—'}</td>
                  <td class="n">{dette > 0 ? <span class="badge badge-rouge">{fcfa(dette)}</span> : <span class="badge badge-vert">0 F</span>}</td>
                </tr>
              );
            })}
            {!liste.length && <tr><td colSpan={3} class="vide">Aucun client.</td></tr>}
          </tbody>
        </table>
      </div>
    </>
  );
}

export function ClientForm({ uuid, retour }) {
  const { naviguer } = useApp();
  const existant = useRequete(() => (uuid ? db.clients.get(uuid) : Promise.resolve(null)), [uuid], undefined);
  const [f, setF] = useState(null);
  const [erreur, setErreur] = useState(null);
  if (existant === undefined) return null;
  const v = f || existant || { nom: '', telephone: '', adresse: '' };
  const champ = (cle) => ({ value: v[cle] || '', onInput: (e) => setF({ ...v, [cle]: e.target.value }) });

  const valider = async (e) => {
    e.preventDefault();
    try {
      const client = await enregistrerClient(v);
      if (retour === 'caisse') naviguer('caisse', { client: client.uuid }, `Client « ${client.nom} » ajouté.`);
      else naviguer('client', { uuid: client.uuid }, 'Client enregistré.');
    } catch (err) { setErreur(err.message); }
  };

  return (
    <>
      <h1>{existant ? `Modifier « ${existant.nom} »` : 'Nouveau client'}</h1>
      <form class="carte formulaire" onSubmit={valider}>
        {erreur && <div class="alerte alerte-erreur">{erreur}</div>}
        <label>Nom</label><input type="text" required maxLength={150} {...champ('nom')} />
        <label>Téléphone</label><input type="tel" maxLength={30} {...champ('telephone')} />
        <label>Adresse / quartier</label><input type="text" maxLength={255} {...champ('adresse')} />
        <div class="actions" style="margin-top:14px">
          <button class="btn btn-ambre">Enregistrer</button>
          <button type="button" class="btn btn-leger" onClick={() => naviguer(retour || (uuid ? 'client' : 'clients'), uuid ? { uuid } : {})}>Annuler</button>
        </div>
      </form>
    </>
  );
}

export function ClientDetail({ uuid }) {
  const { session, naviguer } = useApp();
  const donnees = useRequete(async () => {
    const [client, ventes, remboursements, dette] = await Promise.all([
      db.clients.get(uuid),
      db.ventes.where('client_uuid').equals(uuid).toArray(),
      db.remboursements.where('client_uuid').equals(uuid).toArray(),
      detteClient(uuid),
    ]);
    const historique = [
      ...ventes.map((v) => ({ date: v.created_at, type: 'vente', o: v })),
      ...remboursements.map((r) => ({ date: r.created_at, type: 'remboursement', o: r })),
    ].sort((a, b) => (a.date < b.date ? 1 : -1));
    return { client, dette, historique };
  }, [uuid], null);
  const [montant, setMontant] = useState('');
  const [mode, setMode] = useState('especes');
  const [note, setNote] = useState('');
  const [erreur, setErreur] = useState(null);

  if (!donnees) return null;
  const { client, dette, historique } = donnees;
  if (!client) return <div class="vide">Client introuvable.</div>;

  const valider = async (e) => {
    e.preventDefault();
    setErreur(null);
    try {
      const m = parseInt(montant || dette);
      await rembourser({ clientUuid: uuid, montant: m, mode, note, session });
      setMontant(''); setNote('');
      naviguer('client', { uuid }, `Remboursement de ${fcfa(m)} enregistré.`);
    } catch (err) { setErreur(err.message); }
  };

  return (
    <>
      <div class="entete">
        <h1>{client.nom}</h1>
        <div class="actions">
          <button class="btn btn-leger" onClick={() => naviguer('clientForm', { uuid })}>Modifier</button>
          <button class="btn btn-ambre" onClick={() => naviguer('caisse', { client: uuid })}>Vendre à ce client</button>
        </div>
      </div>
      <div class="deux-col">
        <div>
          <div class="grille"><div class="stat"><div class="lib">Doit actuellement</div><div class={'val ' + (dette > 0 ? 'negatif' : 'positif')}>{fcfa(dette)}</div></div></div>
          <div class="carte">
            <div>📞 {client.telephone ? <a href={'tel:' + client.telephone}>{client.telephone}</a> : 'Pas de téléphone'}</div>
            {client.adresse && <div style="margin-top:6px">📍 {client.adresse}</div>}
          </div>
        </div>
        {dette > 0 && (
          <form class="carte" onSubmit={valider}>
            <h2>Enregistrer un remboursement</h2>
            {erreur && <div class="alerte alerte-erreur">{erreur}</div>}
            <div class="champs">
              <div><label>Montant (F)</label><input type="number" min="1" max={dette} placeholder={String(dette)} value={montant} onInput={(e) => setMontant(e.target.value)} /></div>
              <div><label>Mode</label><select value={mode} onChange={(e) => setMode(e.target.value)}>{Object.entries(MODES).map(([k, l]) => <option key={k} value={k}>{l}</option>)}</select></div>
            </div>
            <label>Note</label><input type="text" maxLength={255} value={note} onInput={(e) => setNote(e.target.value)} />
            <button class="btn btn-ambre" style="margin-top:10px">Enregistrer</button>
          </form>
        )}
      </div>
      <div class="carte tableau">
        <h2>Historique</h2>
        <table>
          <thead><tr><th>Date</th><th>Opération</th><th class="n">Montant</th><th class="n">Crédit / remboursé</th></tr></thead>
          <tbody>
            {historique.map(({ type, o }) => type === 'vente' ? (
              <tr key={o.uuid} class={'clic' + (o.statut === 'annulee' ? ' barre' : '')} onClick={() => naviguer('vente', { uuid: o.uuid })}>
                <td>{dateHeure(o.created_at)}</td><td>Achat {o.numero}</td><td class="n">{fcfa(o.total)}</td>
                <td class="n negatif">{o.total > o.montant_paye ? '+ ' + fcfa(o.total - o.montant_paye) : '—'}</td>
              </tr>
            ) : (
              <tr key={o.uuid}>
                <td>{dateHeure(o.created_at)}</td><td>Remboursement ({MODES[o.mode_paiement]}) {o.note}</td><td></td>
                <td class="n positif">− {fcfa(o.montant)}</td>
              </tr>
            ))}
            {!historique.length && <tr><td colSpan={4} class="vide">Aucune opération.</td></tr>}
          </tbody>
        </table>
      </div>
    </>
  );
}
