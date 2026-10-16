import { liveQuery } from 'dexie';
import { useEffect, useState } from 'preact/hooks';
import { db } from './db.js';
import { surEtat } from './sync.js';

// Relit automatiquement la base locale quand elle change (saisie, synchronisation…)
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

// Tables de référence en mémoire (uuid → fiche) pour afficher les noms de paroisse, CEB, fidèle…
export function useCartes() {
  return useRequete(async () => {
    const carte = async (t) => new Map((await db.table(t).toArray()).map((f) => [f.uuid, f]));
    const [paroisses, cebs, mouvements, fideles] = await Promise.all(['paroisses', 'cebs', 'mouvements', 'fideles'].map(carte));
    return { paroisses, cebs, mouvements, fideles };
  }, [], null);
}
