import { beforeEach, describe, expect, it } from 'vitest';
import { enregistrerFiche } from '../src/actions.js';
import { db } from '../src/db.js';
import { appliquer, regrouper } from '../src/sync.js';
import { comptes, tableauDeBord } from '../src/statistiques.js';
import { cumulsDepots } from '../src/ressources.js';

const admin = { utilisateur: { uuid: 'u-1', role: 'admin', secteur_uuid: null }, appareil: { code: 'A3' } };
const secretaire = { utilisateur: { uuid: 'u-2', role: 'secretaire', secteur_uuid: 'p-a' }, appareil: { code: 'A3' } };
const tresorier = { utilisateur: { uuid: 'u-3', role: 'tresorier', secteur_uuid: 'p-a' }, appareil: { code: 'A3' } };

beforeEach(async () => {
  await Promise.all(db.tables.map((t) => t.clear()));
  await db.secteurs.bulkPut([{ uuid: 'p-a', nom: 'Saint-Paul' }, { uuid: 'p-b', nom: 'Sainte-Marie' }]);
  await db.cebs.bulkPut([{ uuid: 'c-a', secteur_uuid: 'p-a', nom: 'CEB A' }, { uuid: 'c-b', secteur_uuid: 'p-b', nom: 'CEB B' }]);
});

const awa = { secteur_uuid: 'p-a', ceb_uuid: 'c-a', nom: 'Koné', prenoms: 'Awa', sexe: 'F' };

describe('fiches hors ligne', () => {
  it('enregistre un fidèle localement et le met en attente d\'envoi', async () => {
    const f = await enregistrerFiche('fideles', awa, secretaire);
    expect(f.statut).toBe('actif');
    expect(f._local).toBe(true);
    expect((await db.fideles.get(f.uuid)).nom).toBe('Koné');
    const [envoi] = await db.envois.toArray();
    expect(envoi.table).toBe('fideles');
    expect(envoi.donnees.uuid).toBe(f.uuid);
    expect(envoi.donnees._local).toBeUndefined();
  });

  it('refuse les champs obligatoires, une CEB d\'une autre secteur et les droits insuffisants', async () => {
    await expect(enregistrerFiche('fideles', { ...awa, nom: '' }, secretaire)).rejects.toThrow(/Nom : obligatoire/);
    await expect(enregistrerFiche('fideles', { ...awa, ceb_uuid: 'c-b' }, secretaire)).rejects.toThrow(/CEB/);
    await expect(enregistrerFiche('operations', { sens: 'recette', categorie: 'Quête', libelle: 'x', montant: 100, date_operation: '2026-01-01', secteur_uuid: 'p-a' }, secretaire))
      .rejects.toThrow(/pas le droit/);
    await expect(enregistrerFiche('fideles', awa, tresorier)).rejects.toThrow(/pas le droit/);
  });

  it('un compte de secteur travaille toujours dans sa secteur', async () => {
    const f = await enregistrerFiche('fideles', { ...awa, secteur_uuid: 'p-b', ceb_uuid: '' }, secretaire);
    expect(f.secteur_uuid).toBe('p-a');
  });

  it('sacrements : une seule fois, jamais avant la naissance, obsèques = décédé', async () => {
    const f = await enregistrerFiche('fideles', { ...awa, date_naissance: '1990-05-01' }, secretaire);
    const bapteme = { fidele_uuid: f.uuid, type: 'bapteme', date_sacrement: '1990-07-15' };
    const s = await enregistrerFiche('sacrements', bapteme, secretaire);
    expect(s.secteur_uuid).toBe('p-a');
    await expect(enregistrerFiche('sacrements', bapteme, secretaire)).rejects.toThrow(/déjà enregistré/);
    await expect(enregistrerFiche('sacrements', { ...bapteme, type: 'confirmation', date_sacrement: '1980-01-01' }, secretaire)).rejects.toThrow(/antérieur/);

    await enregistrerFiche('sacrements', { fidele_uuid: f.uuid, type: 'obseques', date_sacrement: '2026-01-02' }, secretaire);
    expect((await db.fideles.get(f.uuid)).statut).toBe('decede');
  });

  it('finances : la catégorie doit correspondre au sens', async () => {
    const op = { secteur_uuid: 'p-a', sens: 'depense', categorie: 'Quête', libelle: 'x', montant: 5000, date_operation: '2026-03-01' };
    await expect(enregistrerFiche('operations', op, tresorier)).rejects.toThrow(/catégorie/);
    await enregistrerFiche('operations', { ...op, categorie: 'Transport' }, tresorier);
    expect(await db.operations.count()).toBe(1);
  });
});

describe('synchronisation', () => {
  it('envoie les fiches dans l\'ordre des dépendances, une seule version par fiche', async () => {
    const f = await enregistrerFiche('fideles', awa, secretaire);
    await enregistrerFiche('fideles', { ...awa, prenoms: 'Awa Marie' }, secretaire, f);
    await enregistrerFiche('sacrements', { fidele_uuid: f.uuid, type: 'bapteme', date_sacrement: '2000-01-01' }, secretaire);

    const c = regrouper(await db.envois.orderBy('id').toArray());
    expect(Object.keys(c)).toEqual(['fideles', 'sacrements']);
    expect(c.fideles).toHaveLength(1);
    expect(c.fideles[0].prenoms).toBe('Awa Marie');
  });

  it('après la réponse du serveur, la fiche locale est remplacée et l\'envoi retiré', async () => {
    const f = await enregistrerFiche('fideles', awa, secretaire);
    const envois = await db.envois.toArray();
    await appliquer({
      revision: 7, rejets: [], suppressions: [],
      donnees: { fideles: [{ ...awa, uuid: f.uuid, statut: 'actif', date_naissance: null }] },
    }, envois);

    expect(await db.envois.count()).toBe(0);
    expect((await db.fideles.get(f.uuid))._local).toBeUndefined();
    expect((await db.reglages.get('revision')).valeur).toBe(7);
  });

  it('une fiche créée ici puis refusée par le serveur est retirée et signalée', async () => {
    const f = await enregistrerFiche('fideles', awa, secretaire);
    const envois = await db.envois.toArray();
    await appliquer({ revision: 1, rejets: [{ table: 'fideles', uuid: f.uuid, raison: 'Doublon.' }], donnees: {}, suppressions: [] }, envois);

    expect(await db.fideles.get(f.uuid)).toBeUndefined();
    expect((await db.rejets.toArray())[0].raison).toBe('Doublon.');
  });

  it('les suppressions faites sur le serveur sont appliquées', async () => {
    await appliquer({ revision: 3, rejets: [], donnees: {}, suppressions: [{ table_nom: 'cebs', uuid: 'c-a' }] }, []);
    expect(await db.cebs.get('c-a')).toBeUndefined();
  });
});

describe('tableau de bord local', () => {
  it('compte les fidèles actifs, les CEB et les recettes', async () => {
    await db.fideles.bulkPut([
      { uuid: 'f1', secteur_uuid: 'p-a', sexe: 'F', statut: 'actif', famille: 'Koné' },
      { uuid: 'f2', secteur_uuid: 'p-a', sexe: 'M', statut: 'actif', famille: 'Koné' },
      { uuid: 'f3', secteur_uuid: 'p-b', sexe: 'M', statut: 'actif' },
      { uuid: 'f4', secteur_uuid: 'p-b', sexe: 'M', statut: 'decede' },
    ]);
    const aujourdhui = new Date().toISOString().slice(0, 10);
    await db.operations.bulkPut([
      { uuid: 'o1', secteur_uuid: 'p-a', sens: 'recette', categorie: 'Quête', montant: 25000, date_operation: aujourdhui },
      { uuid: 'o2', secteur_uuid: 'p-a', sens: 'depense', categorie: 'Transport', montant: 10000, date_operation: aujourdhui },
    ]);

    const d = await tableauDeBord(admin);
    expect(d.resume).toMatchObject({ fideles: 3, hommes: 2, femmes: 1, familles: 1, cebs: 2 });
    expect(d.finances.mois.solde).toBe(15000);
    expect(d.parSecteur.map((l) => l.fideles)).toEqual([2, 1]); // Saint-Paul, Sainte-Marie (ordre alphabétique)

    expect((await tableauDeBord(secretaire, 'p-a')).resume.fideles).toBe(2);
    expect((await tableauDeBord(secretaire, 'p-a')).finances).toBeNull();
  });
});

describe('comptes : CEB → secteur → vicariat', () => {
  const op = (o) => ({ date_operation: '2026-03-01', categorie: 'Cotisation de CEB', libelle: 'x', ...o });

  it('un compte de CEB appartient au secteur de la CEB ; la catégorie doit correspondre au sens', async () => {
    const o = await enregistrerFiche('operations', op({ ceb_uuid: 'c-a', sens: 'recette', montant: 10000 }), tresorier);
    expect(o.secteur_uuid).toBe('p-a');
    await expect(enregistrerFiche('operations', op({ ceb_uuid: 'c-b', sens: 'recette', montant: 5000 }), tresorier)).rejects.toThrow(/CEB/);
  });

  it('compile les CEB dans le secteur, puis les secteurs dans le vicariat, après les dépôts', async () => {
    await db.operations.bulkPut([
      op({ uuid: 'o1', secteur_uuid: 'p-a', ceb_uuid: 'c-a', sens: 'recette', montant: 30000 }),
      op({ uuid: 'o2', secteur_uuid: 'p-a', ceb_uuid: null, sens: 'depense', categorie: 'Transport', montant: 5000 }),
      op({ uuid: 'o3', secteur_uuid: 'p-b', ceb_uuid: 'c-b', sens: 'recette', montant: 20000 }),
      op({ uuid: 'o4', secteur_uuid: null, ceb_uuid: null, sens: 'depense', categorie: 'Transport', montant: 2000 }),
    ]);
    await db.depots.bulkPut([
      { uuid: 'd1', secteur_uuid: 'p-a', date_depot: '2026-03-10', montant: 10000 },
      { uuid: 'd2', secteur_uuid: 'p-b', date_depot: '2026-03-11', montant: 20000 },
    ]);

    const c = await comptes(admin);
    const a = c.secteurs.find((l) => l.secteur.uuid === 'p-a');
    expect(a.cebs.find((x) => x.ceb.uuid === 'c-a').solde).toBe(30000);
    expect(a.compile.solde).toBe(25000); // 30 000 − 5 000
    expect(a.reste).toBe(15000); // 25 000 − 10 000 déjà déposés
    expect(c.central).toMatchObject({ recu: 30000, caisse: 28000 });
    expect(c.totalCompile).toBe(25000 + 20000 - 2000);
    expect(c.totalReste).toBe(15000);

    // Un compte de secteur ne voit pas la caisse centrale
    expect((await comptes(tresorier, 'p-a')).central).toBeNull();
  });

  it('les cumuls sont recalculés à chaque dépôt', () => {
    const cumuls = cumulsDepots([
      { uuid: 'd3', secteur_uuid: 'p-a', date_depot: '2026-03-20', montant: 5000 },
      { uuid: 'd1', secteur_uuid: 'p-a', date_depot: '2026-03-10', montant: 10000 },
      { uuid: 'd2', secteur_uuid: 'p-b', date_depot: '2026-03-11', montant: 20000 },
    ]);
    expect(cumuls.get('d1')).toEqual({ cumul_secteur: 10000, total_vicariat: 10000 });
    expect(cumuls.get('d2')).toEqual({ cumul_secteur: 20000, total_vicariat: 30000 });
    expect(cumuls.get('d3')).toEqual({ cumul_secteur: 15000, total_vicariat: 35000 });
  });
});
