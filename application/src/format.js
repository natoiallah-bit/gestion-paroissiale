export const fcfa = (n) => new Intl.NumberFormat('fr-FR').format(Math.round(n || 0)).replace(/ | /g, ' ') + ' F';

export const dateHeure = (iso) => {
  if (!iso) return '';
  const d = new Date(iso);
  return d.toLocaleDateString('fr-FR') + ' ' + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
};

export const dateCourte = (iso) => (iso ? new Date(iso).toLocaleDateString('fr-FR') : '');

// "2026-10-07" dans le fuseau de l'appareil
export const jourLocal = (d = new Date()) => {
  const p = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
};

export const MODES = { especes: 'Espèces', mobile_money: 'Mobile money' };

export const CATEGORIES_DEPENSES = ['Loyer', 'Électricité / eau', 'Transport', 'Salaires', 'Achat marchandise', 'Taxes', 'Téléphone / internet', 'Autre'];

export const ilYa = (iso) => {
  if (!iso) return 'jamais';
  const s = Math.round((Date.now() - new Date(iso).getTime()) / 1000);
  if (s < 60) return "à l'instant";
  if (s < 3600) return `il y a ${Math.round(s / 60)} min`;
  if (s < 86400) return `il y a ${Math.round(s / 3600)} h`;
  return 'le ' + dateHeure(iso);
};
