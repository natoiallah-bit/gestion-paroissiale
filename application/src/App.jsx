import { createContext } from 'preact';
import { useContext, useEffect, useState } from 'preact/hooks';
import { useEtatSync, useRequete } from './crochets.js';
import { db } from './db.js';
import { ilYa } from './format.js';
import { estGerant, lireSession } from './session.js';
import { demarrerAuto, synchroniser } from './sync.js';
import { Accueil } from './ecrans/Accueil.jsx';
import { Caisse } from './ecrans/Caisse.jsx';
import { Clients, ClientDetail, ClientForm } from './ecrans/Clients.jsx';
import { Connexion } from './ecrans/Connexion.jsx';
import { Depenses } from './ecrans/Depenses.jsx';
import { EntreeStock, ProduitDetail, ProduitForm, Produits } from './ecrans/Produits.jsx';
import { Synchro } from './ecrans/Synchro.jsx';
import { Ticket, VenteDetail, Ventes } from './ecrans/Ventes.jsx';

const Contexte = createContext(null);
export const useApp = () => useContext(Contexte);

const ECRANS = {
  accueil: Accueil, caisse: Caisse, ventes: Ventes, vente: VenteDetail, ticket: Ticket,
  clients: Clients, client: ClientDetail, clientForm: ClientForm,
  produits: Produits, produit: ProduitDetail, produitForm: ProduitForm, entree: EntreeStock,
  depenses: Depenses, synchro: Synchro,
};

const MENU = [
  { ecran: 'accueil', ico: '▦', texte: 'Accueil', gerant: true },
  { ecran: 'caisse', ico: '🛒', texte: 'Caisse' },
  { ecran: 'ventes', ico: '🧾', texte: 'Ventes' },
  { ecran: 'clients', ico: '👥', texte: 'Clients' },
  { ecran: 'produits', ico: '📦', texte: 'Produits', gerant: true },
  { ecran: 'depenses', ico: '💸', texte: 'Dépenses', gerant: true },
  { ecran: 'synchro', ico: '⟳', texte: 'Synchro' },
];

export function App() {
  const [session, setSession] = useState(undefined);
  const [page, setPage] = useState({ nom: 'caisse', params: {} });
  const [message, setMessage] = useState(null);

  const recharger = async () => {
    const s = await lireSession();
    setSession(s);
    return s;
  };

  useEffect(() => {
    recharger().then((s) => {
      if (s) {
        if (estGerant(s)) setPage({ nom: 'accueil', params: {} });
        demarrerAuto();
      }
    });
  }, []);

  if (session === undefined) return null;
  if (!session) {
    return <Connexion onConnecte={async () => {
      const s = await recharger();
      setPage({ nom: estGerant(s) ? 'accueil' : 'caisse', params: {} });
      demarrerAuto();
    }} />;
  }

  const naviguer = (nom, params = {}, msg = null) => {
    setPage({ nom, params });
    setMessage(msg);
    window.scrollTo(0, 0);
  };

  const Ecran = ECRANS[page.nom] || Caisse;
  const gerant = estGerant(session);
  const menu = MENU.filter((m) => !m.gerant || gerant);
  const actif = (m) => page.nom === m.ecran || page.nom.startsWith(m.ecran.replace(/s$/, ''));

  return (
    <Contexte.Provider value={{ session, naviguer, recharger, gerant }}>
      <div class="app">
        <nav class="menu">
          <div class="marque"><NomBoutique /><span>Gestion paroissiale</span></div>
          {menu.map((m) => (
            <button key={m.ecran} class={'lien' + (actif(m) ? ' actif' : '')} onClick={() => naviguer(m.ecran)}>
              <span class="ico">{m.ico}</span>{m.texte}
            </button>
          ))}
          <div class="bas">
            <PastilleSync />
            <div style="margin-top:10px"><b style="color:#fff">{session.utilisateur.nom}</b><br />Appareil {session.appareil.code}</div>
          </div>
        </nav>
        <main>
          <div class="haut-mobile"><b><NomBoutique /></b><PastilleSync court /></div>
          {message && <div class="alerte alerte-succes">{message}</div>}
          <Ecran key={page.nom + JSON.stringify(page.params)} {...page.params} />
        </main>
        <nav class="onglets">
          {menu.map((m) => (
            <button key={m.ecran} class={actif(m) ? 'actif' : ''} onClick={() => naviguer(m.ecran)}>
              <span class="ico">{m.ico}</span>{m.texte}
            </button>
          ))}
        </nav>
      </div>
    </Contexte.Provider>
  );
}

function NomBoutique() {
  const boutique = useRequete(() => db.boutique.toCollection().first(), []);
  return <b>{boutique?.nom || 'Ma Boutique'}</b>;
}

export function PastilleSync({ court }) {
  const { naviguer } = useApp();
  const etat = useEtatSync();
  const enAttente = useRequete(() => db.envois.count(), [], 0);
  const derniere = useRequete(() => db.reglages.get('derniere_synchro'), []);
  const [, rafraichir] = useState(0);
  useEffect(() => { const t = setInterval(() => rafraichir((n) => n + 1), 30000); return () => clearInterval(t); }, []);

  let classe = '', texte;
  if (etat.enCours) { classe = 'tourne'; texte = 'Synchronisation…'; }
  else if (etat.erreur) { classe = 'erreur'; texte = court ? 'Erreur' : 'Erreur de synchro'; }
  else if (etat.horsLigne) { classe = 'hors'; texte = enAttente ? `Hors ligne · ${enAttente} en attente` : 'Hors ligne'; }
  else if (enAttente) { classe = 'attente'; texte = `${enAttente} en attente`; }
  else texte = court ? 'À jour' : 'À jour · ' + ilYa(derniere?.valeur);

  return (
    <button class="pastille-sync" onClick={() => { synchroniser().catch(() => {}); naviguer('synchro'); }} title="Synchroniser maintenant">
      <span class={'point ' + classe}></span>{texte}
    </button>
  );
}
