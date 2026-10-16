// Appels au serveur. Toute erreur réseau devient une ErreurReseau : l'appli continue hors ligne.
export class ErreurReseau extends Error {}
export class ErreurSession extends Error {}

export function normaliserServeur(adresse) {
  let a = (adresse || '').trim().replace(/\/+$/, '');
  if (a && !/^https?:\/\//i.test(a)) {
    // Ordinateur de la paroisse (192.168.x.x:8200, localhost…) : http ; serveur en ligne : https
    const local = /^(localhost|127\.|10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)|^[\d.]+(:\d+)?$/i.test(a);
    a = (local ? 'http://' : 'https://') + a;
  }
  return a;
}

export async function appeler(serveur, chemin, corps, jeton, delai = 30000) {
  const controle = new AbortController();
  const minuteur = setTimeout(() => controle.abort(), delai);
  let reponse;
  try {
    reponse = await fetch(serveur + chemin, {
      method: corps === undefined ? 'GET' : 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(jeton ? { Authorization: 'Bearer ' + jeton } : {}),
      },
      body: corps === undefined ? undefined : JSON.stringify(corps),
      signal: controle.signal,
    });
  } catch (e) {
    throw new ErreurReseau('Serveur injoignable. Vérifiez la connexion internet et l\'adresse du serveur.');
  } finally {
    clearTimeout(minuteur);
  }

  let donnees = null;
  try { donnees = await reponse.json(); } catch { /* réponse vide ou HTML */ }

  if (reponse.status === 401) throw new ErreurSession('Session expirée : reconnectez-vous.');
  if (reponse.status === 422) {
    const erreurs = donnees?.errors ? Object.values(donnees.errors).flat() : [donnees?.message];
    throw new Error(erreurs.filter(Boolean).join(' ') || 'Données refusées.');
  }
  if (!reponse.ok) throw new Error(donnees?.message || `Erreur du serveur (${reponse.status}).`);
  if (donnees === null) throw new Error("Cette adresse ne répond pas comme un serveur Gestion paroissiale.");
  return donnees;
}
