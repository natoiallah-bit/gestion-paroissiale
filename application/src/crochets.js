import { liveQuery } from 'dexie';
import { useEffect, useState } from 'preact/hooks';
import { surEtat } from './sync.js';

// Relit automatiquement la base locale quand elle change (vente, synchronisation…)
export function useRequete(requete, deps = [], initial = undefined) {
  const [valeur, setValeur] = useState(initial);
  useEffect(() => {
    const abonnement = liveQuery(requete).subscribe({ next: setValeur, error: (e) => console.error(e) });
    return () => abonnement.unsubscribe();
  }, deps);
  return valeur;
}

export function useEtatSync() {
  const [etat, setEtat] = useState({});
  useEffect(() => surEtat(setEtat), []);
  return etat;
}
