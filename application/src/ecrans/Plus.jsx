import { useApp } from '../App.jsx';
import { peutLire, RESSOURCES } from '../ressources.js';

// Menu complet pour le téléphone (la barre du bas ne contient que l'essentiel)
export function Plus() {
  const { session, naviguer } = useApp();
  return (
    <>
      <h1>Menu</h1>
      <div class="grille">
        {Object.entries(RESSOURCES).filter(([cle]) => peutLire(session.utilisateur.role, cle)).map(([cle, def]) => (
          <button key={cle} class="stat" style="text-align:left; cursor:pointer" onClick={() => naviguer('liste', { cle })}>
            <div class="val" style="font-size:16px">{def.libelle}</div>{def.sous_titre && <div class="det">{def.sous_titre}</div>}
          </button>
        ))}
        <button class="stat" style="text-align:left; cursor:pointer" onClick={() => naviguer('synchro')}><div class="val" style="font-size:16px">Synchronisation</div><div class="det">Envoyer et recevoir les données</div></button>
      </div>
    </>
  );
}
