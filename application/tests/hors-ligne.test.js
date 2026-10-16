import { beforeEach, describe, expect, it } from 'vitest';
import { annulerVente, detteClient, enregistrerClient, enregistrerVente, entreeStock, produitsAvecStock, rembourser } from '../src/actions.js';
import { db } from '../src/db.js';
import { appliquer, regrouper } from '../src/sync.js';


const session = { utilisateur: { uuid: 'u-1' }, appareil: { code: 'A3' } };
const riz = { uuid: 'p-riz', nom: 'Riz', prix_achat: 300, prix_vente: 500, stock: 10, seuil_alerte: 2, unite: 'kg', actif: true };

beforeEach(async () => {
  await Promise.all(db.tables.map((t) => t.clear()));
  await db.produits.put(riz);
});

describe('caisse hors ligne', () => {
  it('enregistre la vente, numérote le ticket et baisse le stock affiché', async () => {
    const v1 = await enregistrerVente({ lignes: [{ produit_uuid: 'p-riz', quantite: 3 }], montantPaye: 2000, mode: 'especes', session });
    const v2 = await enregistrerVente({ lignes: [{ produit_uuid: 'p-riz', quantite: 1 }], montantPaye: 500, mode: 'especes', session });

    expect(v1.numero).toBe('A3-000001');
    expect(v2.numero).toBe('A3-000002');
    expect(v1.total).toBe(1500);
    expect(v1.montant_paye).toBe(1500); // la monnaie rendue n'est pas encaissée
    expect((await produitsAvecStock())[0].stock).toBe(6);
    expect(await db.envois.count()).toBe(2);
  });

  it('refuse un crédit sans client, puis calcule la dette et les remboursements', async () => {
    await expect(enregistrerVente({ lignes: [{ produit_uuid: 'p-riz', quantite: 2 }], montantPaye: 0, mode: 'especes', session }))
      .rejects.toThrow(/choisissez le client/);

    const client = await enregistrerClient({ nom: 'Awa' });
    await enregistrerVente({ lignes: [{ produit_uuid: 'p-riz', quantite: 2 }], montantPaye: 200, mode: 'especes', clientUuid: client.uuid, session });
    expect(await detteClient(client.uuid)).toBe(800);

    await rembourser({ clientUuid: client.uuid, montant: 300, mode: 'mobile_money', session });
    expect(await detteClient(client.uuid)).toBe(500);
    await expect(rembourser({ clientUuid: client.uuid, montant: 600, mode: 'especes', session })).rejects.toThrow(/dépasse/);
  });

  it("l'annulation remet le stock et efface la dette", async () => {
    const client = await enregistrerClient({ nom: 'Moussa' });
    const v = await enregistrerVente({ lignes: [{ produit_uuid: 'p-riz', quantite: 4 }], montantPaye: 0, mode: 'especes', clientUuid: client.uuid, session });
    await annulerVente(v, 'Erreur');
    expect((await produitsAvecStock())[0].stock).toBe(10);
    expect(await detteClient(client.uuid)).toBe(0);
  });

  it('une entrée de marchandise augmente le stock affiché', async () => {
    await entreeStock({ lignes: [{ produit_uuid: 'p-riz', quantite: 5, prix_achat: 320 }], majPrix: true });
    const [p] = await produitsAvecStock();
    expect(p.stock).toBe(15);
    expect(p.prix_achat).toBe(320);
  });
});

describe('synchronisation', () => {
  it('regroupe les envois et ne garde que la dernière version des fiches', () => {
    const c = regrouper([
      { table: 'clients', donnees: { uuid: 'c1', nom: 'A' } },
      { table: 'ventes', donnees: { uuid: 'v1' } },
      { table: 'clients', donnees: { uuid: 'c1', nom: 'B' } },
    ]);
    expect(c.clients).toEqual([{ uuid: 'c1', nom: 'B' }]);
    expect(c.ventes).toHaveLength(1);
  });

  it('après la réponse du serveur, le stock vient du serveur et les envois sont retirés', async () => {
    await enregistrerVente({ lignes: [{ produit_uuid: 'p-riz', quantite: 3 }], montantPaye: 1500, mode: 'especes', session });
    const envois = await db.envois.toArray();

    // Pendant l'envoi, une autre vente est faite : elle doit rester en attente
    await enregistrerVente({ lignes: [{ produit_uuid: 'p-riz', quantite: 1 }], montantPaye: 500, mode: 'especes', session });

    await appliquer({
      revision: 42,
      rejets: [],
      donnees: { produits: [{ ...riz, stock: 7 }], ventes: [] },
      suppressions: [{ table_nom: 'clients', uuid: 'inexistant' }],
    }, envois);

    expect(await db.envois.count()).toBe(1);
    expect((await produitsAvecStock())[0].stock).toBe(6);
    expect((await db.reglages.get('revision')).valeur).toBe(42);
  });

  it('une vente refusée par le serveur est retirée et signalée', async () => {
    const v = await enregistrerVente({ lignes: [{ produit_uuid: 'p-riz', quantite: 1 }], montantPaye: 500, mode: 'especes', session });
    const envois = await db.envois.toArray();
    await appliquer({ revision: 1, rejets: [{ table: 'ventes', uuid: v.uuid, raison: 'Produit inconnu du serveur.' }], donnees: {}, suppressions: [] }, envois);

    expect(await db.ventes.get(v.uuid)).toBeUndefined();
    expect((await db.rejets.toArray())[0].raison).toMatch(/inconnu/);
  });
});
