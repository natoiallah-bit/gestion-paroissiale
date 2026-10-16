import { useMemo, useRef, useState } from 'preact/hooks';
import { useApp } from '../App.jsx';
import { enregistrerVente, produitsAvecStock } from '../actions.js';
import { useRequete } from '../crochets.js';
import { db } from '../db.js';
import { fcfa, MODES } from '../format.js';

// Le panier survit au passage sur un autre écran (ex. création d'un client)
let memoire = { panier: [], clientUuid: '' };

export function Caisse({ client }) {
  const { session, naviguer } = useApp();
  const produits = useRequete(() => produitsAvecStock(), [], []);
  const clients = useRequete(() => db.clients.orderBy('nom').toArray(), [], []);
  const [panier, setPanierEtat] = useState(memoire.panier); // [{produit_uuid, quantite}]
  const setPanier = (f) => setPanierEtat((actuel) => (memoire.panier = typeof f === 'function' ? f(actuel) : f));
  const [recherche, setRecherche] = useState('');
  const [clientUuid, setClientUuidEtat] = useState(() => (memoire.clientUuid = client || memoire.clientUuid));
  const setClientUuid = (v) => { memoire.clientUuid = v; setClientUuidEtat(v); };
  const [mode, setMode] = useState('especes');
  const [recu, setRecu] = useState(null); // null = égal au total
  const [erreur, setErreur] = useState(null);
  const [envoi, setEnvoi] = useState(false);
  const panierRef = useRef(null);

  const parUuid = useMemo(() => Object.fromEntries(produits.map((p) => [p.uuid, p])), [produits]);
  const lignes = panier.filter((l) => parUuid[l.produit_uuid]);
  const total = lignes.reduce((s, l) => s + l.quantite * parUuid[l.produit_uuid].prix_vente, 0);
  const montantRecu = recu === null ? total : recu;
  const nbArticles = lignes.reduce((s, l) => s + l.quantite, 0);

  const q = recherche.trim().toLowerCase();
  const visibles = q ? produits.filter((p) => (p.nom + ' ' + (p.code || '')).toLowerCase().includes(q)) : produits;

  const changer = (uuid, quantite) => {
    setErreur(null);
    setPanier((actuel) => {
      if (quantite <= 0) return actuel.filter((l) => l.produit_uuid !== uuid);
      return actuel.some((l) => l.produit_uuid === uuid)
        ? actuel.map((l) => (l.produit_uuid === uuid ? { ...l, quantite } : l))
        : [...actuel, { produit_uuid: uuid, quantite }];
    });
  };
  const quantite = (uuid) => panier.find((l) => l.produit_uuid === uuid)?.quantite || 0;
  const ajouter = (p) => changer(p.uuid, quantite(p.uuid) + 1);

  // Lecteur de code-barres : il tape le code puis "Entrée"
  const surEntree = (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const exact = produits.find((p) => p.code && p.code.toLowerCase() === q);
    const choix = exact || (visibles.length === 1 ? visibles[0] : null);
    if (choix) { ajouter(choix); setRecherche(''); }
  };

  const valider = async () => {
    setErreur(null);
    const manque = lignes.filter((l) => l.quantite > parUuid[l.produit_uuid].stock);
    if (manque.length && !confirm('Stock affiché insuffisant pour : ' + manque.map((l) => parUuid[l.produit_uuid].nom).join(', ') + '.\nEnregistrer la vente quand même ?')) return;
    setEnvoi(true);
    try {
      const vente = await enregistrerVente({ lignes, clientUuid, montantPaye: montantRecu, mode, session });
      setPanier([]); setRecu(null); setClientUuid('');
      naviguer('ticket', { uuid: vente.uuid, nouvelle: true });
    } catch (e) {
      setErreur(e.message);
    } finally {
      setEnvoi(false);
    }
  };

  return (
    <div class="caisse">
      <section>
        <h1 style="margin-bottom:10px">Caisse</h1>
        <input type="search" placeholder="Rechercher ou scanner un code-barres puis Entrée…" value={recherche}
               onInput={(e) => setRecherche(e.target.value)} onKeyDown={surEntree} />
        <div class="catalogue">
          {visibles.map((p) => (
            <button key={p.uuid} class={'tuile' + (p.stock <= 0 ? ' epuise' : '')} onClick={() => ajouter(p)}>
              <div class="nom">{p.nom}</div>
              <div class="prix">{fcfa(p.prix_vente)}</div>
              <div class="stk">Stock : {p.stock} {p.unite}{quantite(p.uuid) ? ` · ${quantite(p.uuid)} au panier` : ''}</div>
            </button>
          ))}
          {!produits.length && <div class="vide">Aucun produit. Ajoutez-en dans « Produits » (gérant) puis synchronisez.</div>}
        </div>
      </section>

      <div class="carte panier" ref={panierRef}>
        <h2>Panier</h2>
        {erreur && <div class="alerte alerte-erreur">{erreur}</div>}
        {lignes.length ? (
          <table><tbody>
            {lignes.map((l) => {
              const p = parUuid[l.produit_uuid];
              return (
                <tr key={l.produit_uuid}>
                  <td>{p.nom}<div class="aide">{fcfa(p.prix_vente)}</div></td>
                  <td><div class="qte">
                    <button onClick={() => changer(p.uuid, l.quantite - 1)}>−</button>
                    <input type="number" min="0" value={l.quantite} onChange={(e) => changer(p.uuid, Math.max(0, parseInt(e.target.value) || 0))} />
                    <button onClick={() => changer(p.uuid, l.quantite + 1)}>+</button>
                  </div></td>
                  <td class="n">{fcfa(l.quantite * p.prix_vente)}</td>
                </tr>
              );
            })}
          </tbody></table>
        ) : <div class="vide">Touchez un produit pour l'ajouter.</div>}

        <div class="total-gros">{fcfa(total)}</div>

        <label>Client <span class="aide">(obligatoire pour un crédit)</span></label>
        <select value={clientUuid} onChange={(e) => setClientUuid(e.target.value)}>
          <option value="">— Client de passage —</option>
          {clients.map((c) => <option key={c.uuid} value={c.uuid}>{c.nom}{c.telephone ? ' · ' + c.telephone : ''}</option>)}
        </select>
        <div class="aide"><a onClick={() => naviguer('clientForm', { retour: 'caisse' })}>+ Nouveau client</a></div>

        <div class="champs">
          <div>
            <label>Paiement</label>
            <select value={mode} onChange={(e) => setMode(e.target.value)}>
              {Object.entries(MODES).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </select>
          </div>
          <div>
            <label>Montant reçu</label>
            <input type="number" min="0" inputMode="numeric" value={montantRecu} onInput={(e) => setRecu(e.target.value === '' ? 0 : parseInt(e.target.value) || 0)} />
          </div>
        </div>
        <div class="resume"><span>Monnaie à rendre</span><b>{fcfa(Math.max(montantRecu - total, 0))}</b></div>
        <div class="resume"><span>Reste à crédit</span><b class="negatif">{fcfa(Math.max(total - montantRecu, 0))}</b></div>

        <button class="btn btn-ambre btn-large" style="margin-top:12px" disabled={!lignes.length || envoi} onClick={valider}>Valider la vente</button>
      </div>

      {lignes.length > 0 && (
        <button class="barre-panier" onClick={() => panierRef.current?.scrollIntoView({ behavior: 'smooth' })}>
          <span>Panier ({nbArticles} article{nbArticles > 1 ? 's' : ''})</span><span>{fcfa(total)}</span>
        </button>
      )}
    </div>
  );
}
