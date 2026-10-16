import { useEffect, useState } from 'preact/hooks';
import { seConnecter } from '../session.js';
import { synchroniser } from '../sync.js';
import { db, lire } from '../db.js';

const nomParDefaut = () => (/android/i.test(navigator.userAgent) ? 'Téléphone' : 'Ordinateur');

export function Connexion({ onConnecte }) {
  const [f, setF] = useState({ serveur: '', email: '', password: '', nomAppareil: nomParDefaut() });
  const [erreur, setErreur] = useState(null);
  const [etape, setEtape] = useState(null);
  const champ = (cle) => ({ value: f[cle], onInput: (e) => setF({ ...f, [cle]: e.target.value }) });

  useEffect(() => { lire('serveur').then((s) => s && setF((x) => ({ ...x, serveur: s }))); }, []);

  const valider = async (e) => {
    e.preventDefault();
    setErreur(null);
    try {
      const ancien = await lire('serveur');
      const enAttente = await db.envois.count();
      if (enAttente && ancien && ancien.replace(/\/+$/, '') !== f.serveur.trim().replace(/\/+$/, '')) {
        throw new Error(`${enAttente} opération(s) de ce poste ne sont pas encore envoyées à ${ancien}. Reconnectez-vous d'abord à ce serveur pour les synchroniser.`);
      }
      setEtape('Connexion…');
      await seConnecter(f);
      setEtape('Téléchargement des données…');
      await synchroniser();
      onConnecte();
    } catch (err) {
      setErreur(err.message);
      setEtape(null);
    }
  };

  return (
    <div style="min-height:100vh; display:flex; align-items:center; justify-content:center; background:var(--vert-950); padding:16px">
      <form class="carte" style="width:100%; max-width:390px; padding:28px" onSubmit={valider}>
        <h1 style="text-align:center; margin-bottom:4px">Gestion Paroissiale</h1>
        <p class="aide" style="text-align:center; margin-bottom:12px">Connectez cet appareil au serveur de votre paroisse ou de votre vicariat. Le serveur doit être joignable cette première fois ; ensuite l'application marche aussi sans connexion.</p>
        {erreur && <div class="alerte alerte-erreur">{erreur}</div>}
        <label>Adresse du serveur</label>
        <input type="text" inputMode="url" placeholder="192.168.1.20:8200" required {...champ('serveur')} autocapitalize="off" />
        <div class="aide">L'adresse affichée sur l'ordinateur de la paroisse (page Appareils, ou fenêtre de demarrer.bat).</div>
        <label>E-mail</label>
        <input type="email" required {...champ('email')} autocapitalize="off" />
        <label>Mot de passe</label>
        <input type="password" required {...champ('password')} />
        <label>Nom de cet appareil</label>
        <input type="text" required maxLength={100} {...champ('nomAppareil')} />
        <button class="btn btn-ambre btn-large" style="margin-top:16px" disabled={!!etape}>{etape || 'Se connecter'}</button>
      </form>
    </div>
  );
}
