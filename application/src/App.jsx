import { createContext } from 'preact';
import { useContext, useEffect, useState } from 'preact/hooks';
import { useEtatSync, useRequete } from './crochets.js';
import { db } from './db.js';
import { ilYa } from './format.js';
import { peutLire, RESSOURCES } from './ressources.js';
import { lireSession } from './session.js';
import { demarrerAuto, synchroniser } from './sync.js';
import { Accueil } from './ecrans/Accueil.jsx';
import { Comptes } from './ecrans/Comptes.jsx';
import { Connexion } from './ecrans/Connexion.jsx';
import { Extrait } from './ecrans/Extrait.jsx';
import { Fiche } from './ecrans/Fiche.jsx';
import { Formulaire } from './ecrans/Formulaire.jsx';
import { Liste } from './ecrans/Liste.jsx';
import { Plus } from './ecrans/Plus.jsx';
import { Synchro } from './ecrans/Synchro.jsx';

const Contexte = createContext(null);
export const useApp = () => useContext(Contexte);

const ECRANS = { accueil: Accueil, comptes: Comptes, liste: Liste, fiche: Fiche, formulaire: Formulaire, extrait: Extrait, plus: Plus, synchro: Synchro };

const ICONES = { fideles: '👥', cebs: '🏘', mouvements: '🤝', sacrements: '✝', annonces: '📢', activites: '📅', operations: '💰', depots: '🏦', secteurs: '⛪', groupes: '📖', inscriptions: '🧒', seances: '🗓', catechistes: '🧑‍🏫' };
// Ordre du menu de l'ordinateur ; le titre court sert à la barre du bas (téléphone)
const ORDRE = ['fideles', 'cebs', 'mouvements', 'sacrements', 'groupes', 'inscriptions', 'seances', 'catechistes', 'annonces', 'activites', 'operations', 'depots', 'secteurs'];
const COURT = { activites: 'Programme', operations: 'Comptes', depots: 'Dépôts', mouvements: 'Mouvements', groupes: 'Catéchèse', inscriptions: 'Inscrits', seances: 'Séances', catechistes: 'Catéchistes' };
// Barre du bas : Accueil + les 3 premiers écrans autorisés + Menu
const PRIORITE_MOBILE = ['fideles', 'groupes', 'sacrements', 'operations', 'depots', 'activites'];

export function App() {
  const [session, setSession] = useState(undefined);
  const [page, setPage] = useState({ nom: 'accueil', params: {} });
  const [message, setMessage] = useState(null);

  const recharger = async () => {
    const s = await lireSession();
    setSession(s);
    return s;
  };

  useEffect(() => {
    recharger().then((s) => { if (s) demarrerAuto(); });
  }, []);

  if (session === undefined) return null;
  if (!session) {
    return <Connexion onConnecte={async () => {
      await recharger();
      setPage({ nom: 'accueil', params: {} });
      demarrerAuto();
    }} />;
  }

  const naviguer = (nom, params = {}, msg = null) => {
    setPage({ nom, params });
    setMessage(msg);
    window.scrollTo(0, 0);
  };

  const Ecran = ECRANS[page.nom] || Accueil;
  const role = session.utilisateur.role;
  const autorises = ORDRE.filter((cle) => peutLire(role, cle));
  const menu = [
    { id: 'accueil', ico: '▦', texte: 'Accueil', va: ['accueil'] },
    ...autorises.map((cle) => ({ id: cle, ico: ICONES[cle], texte: RESSOURCES[cle].libelle, court: COURT[cle] || RESSOURCES[cle].libelle, va: ['liste', cle] })),
    { id: 'synchro', ico: '⟳', texte: 'Synchro', va: ['synchro'] },
  ];
  const actif = (m) => {
    const cleCourante = page.params.cle;
    if (m.id === 'accueil') return page.nom === 'accueil' || page.nom === 'comptes';
    if (m.id === 'synchro') return page.nom === 'synchro';
    if (page.nom === 'extrait') return m.id === 'sacrements';
    return cleCourante === m.id;
  };
  const ouvrir = (m) => (m.id === 'accueil' ? naviguer('accueil') : m.id === 'synchro' ? naviguer('synchro') : naviguer('liste', { cle: m.id }));
  const mobile = [menu[0], ...PRIORITE_MOBILE.filter((c) => autorises.includes(c)).slice(0, 3).map((c) => menu.find((m) => m.id === c)),
    { id: 'plus', ico: '☰', court: 'Menu', texte: 'Menu' }];

  return (
    <Contexte.Provider value={{ session, naviguer, recharger }}>
      <div class="app">
        <nav class="menu">
          <div class="marque"><NomVicariat /><span>Gestion paroissiale</span></div>
          {menu.map((m) => (
            <button key={m.id} class={'lien' + (actif(m) ? ' actif' : '')} onClick={() => ouvrir(m)}>
              <span class="ico">{m.ico}</span>{m.texte}
            </button>
          ))}
          <div class="bas">
            <PastilleSync />
            <div style="margin-top:10px"><b style="color:#fff">{session.utilisateur.nom}</b><br />Appareil {session.appareil.code}</div>
          </div>
        </nav>
        <main>
          <div class="haut-mobile"><b><NomVicariat /></b><PastilleSync court /></div>
          {message && <div class="alerte alerte-succes">{message}</div>}
          <Ecran key={page.nom + JSON.stringify(page.params)} {...page.params} />
        </main>
        <nav class="onglets">
          {mobile.map((m) => (
            <button key={m.id} class={(m.id === 'plus' ? page.nom === 'plus' : actif(m)) ? 'actif' : ''}
              onClick={() => (m.id === 'plus' ? naviguer('plus') : ouvrir(m))}>
              <span class="ico">{m.ico}</span>{m.court || m.texte}
            </button>
          ))}
        </nav>
      </div>
    </Contexte.Provider>
  );
}

function NomVicariat() {
  const vicariat = useRequete(() => db.vicariat.toCollection().first(), []);
  return <b>{vicariat?.nom || 'Vicariat'}</b>;
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
