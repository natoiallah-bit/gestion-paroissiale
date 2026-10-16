import { Capacitor } from '@capacitor/core';
import { Share } from '@capacitor/share';
import { useState } from 'preact/hooks';
import { useApp } from '../App.jsx';
import { annulerVente } from '../actions.js';
import { useRequete } from '../crochets.js';
import { db } from '../db.js';
import { dateHeure, fcfa, jourLocal, MODES } from '../format.js';

async function chargerNoms() {
  const [clients, utilisateurs] = await Promise.all([db.clients.toArray(), db.utilisateurs.toArray()]);
  return {
    client: Object.fromEntries(clients.map((c) => [c.uuid, c.nom])),
    utilisateur: Object.fromEntries(utilisateurs.map((u) => [u.uuid, u.nom])),
  };
}

export function Ventes() {
  const { session, naviguer, gerant } = useApp();
  const [du, setDu] = useState(jourLocal());
  const [au, setAu] = useState(jourLocal());
  const donnees = useRequete(async () => {
    const debut = new Date(du + 'T00:00:00').toISOString();
    const fin = new Date(au + 'T23:59:59.999').toISOString();
    let ventes = await db.ventes.where('created_at').between(debut, fin, true, true).reverse().toArray();
    // Le vendeur ne voit que ses propres ventes
    if (!gerant) ventes = ventes.filter((v) => v.user_uuid === session.utilisateur.uuid);
    const envois = new Set((await db.envois.where('table').equals('ventes').toArray()).map((e) => e.uuid));
    return { ventes, envois, noms: await chargerNoms() };
  }, [du, au], null);

  if (!donnees) return null;
  const valides = donnees.ventes.filter((v) => v.statut === 'validee');
  const total = valides.reduce((s, v) => s + v.total, 0);
  const encaisse = valides.reduce((s, v) => s + v.montant_paye, 0);

  return (
    <>
      <div class="entete">
        <h1>{gerant ? 'Ventes' : 'Mes ventes'}</h1>
        <button class="btn btn-ambre" onClick={() => naviguer('caisse')}>Nouvelle vente</button>
      </div>
      <div class="champs" style="margin-bottom:12px; max-width:420px">
        <div><label>Du</label><input type="date" value={du} onChange={(e) => setDu(e.target.value)} /></div>
        <div><label>Au</label><input type="date" value={au} onChange={(e) => setAu(e.target.value)} /></div>
      </div>
      <div class="grille">
        <div class="stat"><div class="lib">Ventes</div><div class="val">{valides.length}</div></div>
        <div class="stat"><div class="lib">Total vendu</div><div class="val">{fcfa(total)}</div></div>
        <div class="stat"><div class="lib">Encaissé</div><div class="val">{fcfa(encaisse)}</div></div>
        <div class="stat"><div class="lib">À crédit</div><div class="val negatif">{fcfa(total - encaisse)}</div></div>
      </div>
      <div class="carte tableau">
        <table>
          <thead><tr><th>N°</th><th>Heure</th><th>Client</th><th class="n">Total</th><th>État</th></tr></thead>
          <tbody>
            {donnees.ventes.map((v) => (
              <tr key={v.uuid} class={'clic' + (v.statut === 'annulee' ? ' barre' : '')} onClick={() => naviguer('vente', { uuid: v.uuid })}>
                <td>{v.numero}{donnees.envois.has(v.uuid) && <span class="badge badge-ambre" style="margin-left:6px" title="Pas encore envoyée au serveur">⟳</span>}</td>
                <td>{dateHeure(v.created_at)}</td>
                <td>{donnees.noms.client[v.client_uuid] || '—'}</td>
                <td class="n">{fcfa(v.total)}</td>
                <td>{v.statut === 'annulee' ? <span class="badge badge-gris">Annulée</span>
                  : v.total > v.montant_paye ? <span class="badge badge-ambre">Crédit {fcfa(v.total - v.montant_paye)}</span>
                  : <span class="badge badge-vert">Payée</span>}</td>
              </tr>
            ))}
            {!donnees.ventes.length && <tr><td colSpan={5} class="vide">Aucune vente sur cette période.</td></tr>}
          </tbody>
        </table>
      </div>
    </>
  );
}

export function VenteDetail({ uuid }) {
  const { naviguer, gerant } = useApp();
  const donnees = useRequete(async () => ({ vente: await db.ventes.get(uuid), noms: await chargerNoms() }), [uuid], null);
  const [motif, setMotif] = useState('');
  if (!donnees) return null;
  const { vente, noms } = donnees;
  if (!vente) return <div class="vide">Vente introuvable.</div>;

  const annuler = async (e) => {
    e.preventDefault();
    if (!confirm('Annuler cette vente ? Les articles seront remis en stock.')) return;
    await annulerVente(vente, motif);
    naviguer('vente', { uuid }, 'Vente annulée.');
  };

  return (
    <>
      <div class="entete">
        <h1>Vente {vente.numero} {vente.statut === 'annulee' && <span class="badge badge-gris">Annulée</span>}</h1>
        <div class="actions">
          <button class="btn btn-leger" onClick={() => naviguer('ticket', { uuid })}>Ticket</button>
          <button class="btn btn-leger" onClick={() => naviguer('ventes')}>Retour</button>
        </div>
      </div>
      <div class="grille">
        <div class="stat"><div class="lib">Date</div><div class="val" style="font-size:16px">{dateHeure(vente.created_at)}</div><div class="det">par {noms.utilisateur[vente.user_uuid] || '—'}</div></div>
        <div class="stat"><div class="lib">Client</div><div class="val" style="font-size:16px">
          {vente.client_uuid ? <a onClick={() => naviguer('client', { uuid: vente.client_uuid })}>{noms.client[vente.client_uuid]}</a> : 'Client de passage'}</div></div>
        <div class="stat"><div class="lib">Total</div><div class="val">{fcfa(vente.total)}</div></div>
        <div class="stat"><div class="lib">Payé ({MODES[vente.mode_paiement]})</div><div class="val">{fcfa(vente.montant_paye)}</div>
          {vente.total > vente.montant_paye && <div class="det negatif">Crédit : {fcfa(vente.total - vente.montant_paye)}</div>}</div>
      </div>
      <div class="carte tableau">
        <table>
          <thead><tr><th>Article</th><th class="n">Qté</th><th class="n">Prix</th><th class="n">Total</th></tr></thead>
          <tbody>{vente.lignes.map((l) => (
            <tr key={l.uuid}><td>{l.designation}</td><td class="n">{l.quantite}</td><td class="n">{fcfa(l.prix_unitaire)}</td><td class="n">{fcfa(l.total)}</td></tr>
          ))}</tbody>
        </table>
      </div>
      {vente.statut === 'annulee' ? (
        <div class="carte">Motif d'annulation : {vente.motif_annulation}</div>
      ) : gerant && (
        <form class="carte formulaire" onSubmit={annuler}>
          <h2>Annuler la vente</h2>
          <p class="aide">Les articles retournent en stock et le crédit éventuel du client est effacé.</p>
          <label>Motif</label>
          <input type="text" required maxLength={255} value={motif} onInput={(e) => setMotif(e.target.value)} placeholder="Erreur de saisie, retour client…" />
          <button class="btn btn-danger" style="margin-top:10px">Annuler la vente</button>
        </form>
      )}
    </>
  );
}

export function texteTicket(vente, boutique, noms) {
  const l = [boutique?.nom || 'Ma Boutique'];
  if (boutique?.telephone) l.push('Tél : ' + boutique.telephone);
  l.push('', `Ticket ${vente.numero} — ${dateHeure(vente.created_at)}`);
  if (vente.client_uuid) l.push('Client : ' + (noms.client[vente.client_uuid] || ''));
  if (vente.statut === 'annulee') l.push('*** VENTE ANNULÉE ***');
  l.push('');
  vente.lignes.forEach((x) => l.push(`${x.designation}\n  ${x.quantite} × ${fcfa(x.prix_unitaire)} = ${fcfa(x.total)}`));
  l.push('', 'TOTAL : ' + fcfa(vente.total), `Payé (${MODES[vente.mode_paiement]}) : ${fcfa(vente.montant_paye)}`);
  if (vente.total > vente.montant_paye) l.push('Reste à payer : ' + fcfa(vente.total - vente.montant_paye));
  l.push('', boutique?.pied_ticket || 'Merci de votre visite !');
  return l.join('\n');
}

export function Ticket({ uuid, nouvelle }) {
  const { naviguer } = useApp();
  const donnees = useRequete(async () => ({
    vente: await db.ventes.get(uuid), boutique: await db.boutique.toCollection().first(), noms: await chargerNoms(),
  }), [uuid], null);
  if (!donnees) return null;
  const { vente, boutique, noms } = donnees;
  if (!vente) return <div class="vide">Vente introuvable.</div>;

  const partager = async () => {
    const text = texteTicket(vente, boutique, noms);
    if (Capacitor.isNativePlatform()) await Share.share({ title: 'Ticket ' + vente.numero, text });
    else if (navigator.share) await navigator.share({ title: 'Ticket ' + vente.numero, text }).catch(() => {});
    else { await navigator.clipboard?.writeText(text); alert('Ticket copié : collez-le dans WhatsApp ou un SMS.'); }
  };

  return (
    <>
      {nouvelle && <div class="alerte alerte-succes pas-imprimer">Vente {vente.numero} enregistrée.</div>}
      <div class="ticket">
        <div class="c g" style="font-size:15px">{boutique?.nom || 'Ma Boutique'}</div>
        {boutique?.adresse && <div class="c">{boutique.adresse}</div>}
        {boutique?.telephone && <div class="c">Tél : {boutique.telephone}</div>}
        <hr />
        <div>Ticket : {vente.numero}</div>
        <div>Date : {dateHeure(vente.created_at)}</div>
        {vente.client_uuid && <div>Client : {noms.client[vente.client_uuid]}</div>}
        {vente.statut === 'annulee' && <div class="c g">*** VENTE ANNULÉE ***</div>}
        <hr />
        <table><tbody>
          {vente.lignes.map((x) => [
            <tr key={x.uuid + 'a'}><td colSpan={2}>{x.designation}</td></tr>,
            <tr key={x.uuid + 'b'}><td>&nbsp;&nbsp;{x.quantite} × {fcfa(x.prix_unitaire)}</td><td style="text-align:right">{fcfa(x.total)}</td></tr>,
          ])}
        </tbody></table>
        <hr />
        <table><tbody>
          <tr class="g"><td>TOTAL</td><td style="text-align:right">{fcfa(vente.total)}</td></tr>
          <tr><td>Payé ({MODES[vente.mode_paiement]})</td><td style="text-align:right">{fcfa(vente.montant_paye)}</td></tr>
          {vente.total > vente.montant_paye && <tr class="g"><td>Reste à payer</td><td style="text-align:right">{fcfa(vente.total - vente.montant_paye)}</td></tr>}
        </tbody></table>
        <hr />
        <div class="c">{boutique?.pied_ticket || 'Merci de votre visite !'}</div>
      </div>
      <div class="actions pas-imprimer" style="justify-content:center">
        {!Capacitor.isNativePlatform() && <button class="btn" onClick={() => window.print()}>Imprimer</button>}
        <button class="btn" onClick={partager}>Partager (WhatsApp, SMS…)</button>
        <button class="btn btn-ambre" onClick={() => naviguer('caisse')}>Nouvelle vente</button>
        <button class="btn btn-leger" onClick={() => naviguer('vente', { uuid })}>Détail</button>
      </div>
    </>
  );
}
