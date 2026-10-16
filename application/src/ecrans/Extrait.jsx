import { useApp } from '../App.jsx';
import { useCartes, useRequete } from '../crochets.js';
import { db } from '../db.js';
import { SACREMENTS } from '../ressources.js';

const long = (s) => (s ? new Date(s + 'T12:00:00').toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }) : '');

// Extrait de registre d'un sacrement, à imprimer (ordinateur) ou à partager (téléphone)
export function Extrait({ uuid }) {
  const { naviguer } = useApp();
  const cartes = useCartes();
  const donnees = useRequete(async () => {
    const sacrement = await db.sacrements.get(uuid);
    return { sacrement, vicariat: await db.vicariat.toCollection().first() };
  }, [uuid], null);
  if (!donnees || !cartes) return null;
  const { sacrement: s, vicariat } = donnees;
  if (!s) return <div class="carte vide">Sacrement introuvable.</div>;
  const f = cartes.fideles.get(s.fidele_uuid) || {};
  const p = cartes.secteurs.get(s.secteur_uuid) || {};
  const lignes = [
    ['Nom et prénoms', `${f.prenoms || ''} ${String(f.nom || '').toUpperCase()}`.trim()],
    ['Sexe', f.sexe === 'M' ? 'Masculin' : 'Féminin'],
    f.date_naissance && ['Né(e) le', long(f.date_naissance)],
    ['Date du sacrement', long(s.date_sacrement)],
    s.lieu && ['Lieu', s.lieu], s.ministre && ['Ministre', s.ministre],
    s.parrain && ['Parrain / marraine / témoins', s.parrain], s.conjoint && ['Conjoint', s.conjoint],
    s.numero_registre && ['Registre n°', s.numero_registre],
  ].filter(Boolean);

  const texte = `Extrait du registre — ${SACREMENTS[s.type]}\nVicariat ${vicariat?.nom || ''} · Secteur ${p.nom || ''}\n` + lignes.map(([a, b]) => `${a} : ${b}`).join('\n');
  const partager = async () => {
    try {
      const { Share } = await import('@capacitor/share');
      await Share.share({ title: 'Extrait de registre', text: texte });
    } catch { window.print(); }
  };

  return (
    <>
      <div class="actions pas-imprimer" style="margin-bottom:12px">
        <button class="btn btn-leger" onClick={() => naviguer('fiche', { cle: 'sacrements', uuid })}>← Retour</button>
        <button class="btn" onClick={() => window.print()}>Imprimer</button>
        <button class="btn btn-leger" onClick={partager}>Partager</button>
      </div>
      <div class="carte" style="max-width:700px; font-family:Georgia, serif; line-height:1.6">
        <div style="text-align:center; border-bottom:2px solid #1e2420; padding-bottom:10px; margin-bottom:18px">
          {vicariat?.archidiocese && <div style="text-transform:uppercase; font-size:12px; letter-spacing:1px">Archidiocèse de {vicariat.archidiocese}</div>}
          {vicariat?.doyenne && <div style="text-transform:uppercase; font-size:12px; letter-spacing:1px">Doyenné de {vicariat.doyenne}</div>}
          <div style="font-size:20px; font-weight:bold">Vicariat {vicariat?.nom}</div>
          <div style="text-transform:uppercase; font-size:12px; letter-spacing:1px">Secteur {p.nom}{cartes.cebs.get(f.ceb_uuid) ? ' — ' + cartes.cebs.get(f.ceb_uuid).nom : ''}</div>
        </div>
        <h2 style="text-align:center; text-transform:uppercase; margin-bottom:18px">Extrait du registre — {SACREMENTS[s.type]}</h2>
        {lignes.map(([a, b]) => <div key={a} style="display:flex; gap:12px; margin-bottom:4px"><span style="width:220px; color:#555">{a}</span><b>{b}</b></div>)}
        <div style="display:flex; justify-content:space-between; margin-top:40px"><span>Fait à {vicariat?.nom}, le {long(new Date().toISOString().slice(0, 10))}</span><span>Le curé{vicariat?.responsable ? ' : ' + vicariat.responsable : ''}</span></div>
        <div style="margin-top:24px; text-align:center; font-size:12px; color:#555">{vicariat?.pied_extrait || 'Pour copie conforme au registre.'}</div>
      </div>
    </>
  );
}
