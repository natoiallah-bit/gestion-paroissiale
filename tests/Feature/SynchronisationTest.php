<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Produit;
use App\Models\User;
use App\Models\Vente;
use App\Support\Synchronisation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SynchronisationTest extends TestCase
{
    use RefreshDatabase;

    private function connecter(User $user, ?string $appareil = null): array
    {
        $reponse = $this->postJson('/api/connexion', [
            'email' => $user->email,
            'password' => 'password',
            'appareil_uuid' => $appareil ?? (string) Str::uuid(),
            'appareil_nom' => 'Téléphone test',
        ])->assertOk();

        return $reponse->json();
    }

    private function synchro(string $jeton, int $depuis = 0, array $changements = [])
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($jeton)->postJson('/api/synchroniser', ['depuis' => $depuis, 'changements' => $changements]);
    }

    public function test_connexion_refuse_un_mauvais_mot_de_passe_et_attribue_un_code_appareil(): void
    {
        $user = User::factory()->create();
        $this->postJson('/api/connexion', ['email' => $user->email, 'password' => 'faux', 'appareil_uuid' => (string) Str::uuid(), 'appareil_nom' => 'x'])
            ->assertStatus(422);

        $a = $this->connecter($user);
        $b = $this->connecter($user);
        $this->assertSame('A1', $a['appareil']['code']);
        $this->assertSame('A2', $b['appareil']['code']);

        $this->withToken('jeton-invalide')->postJson('/api/synchroniser', ['depuis' => 0])->assertUnauthorized();
    }

    public function test_un_appareil_recoit_le_catalogue_puis_seulement_les_changements(): void
    {
        $gerant = User::factory()->gerant()->create();
        $produit = Produit::create(['nom' => 'Riz', 'prix_achat' => 300, 'prix_vente' => 500, 'unite' => 'kg', 'seuil_alerte' => 1, 'actif' => true]);
        $produit->mouvementer(10, 'entree', $gerant->id);
        $jeton = $this->connecter($gerant)['jeton'];

        $r1 = $this->synchro($jeton)->assertOk()->json();
        $this->assertSame(10, $r1['donnees']['produits'][0]['stock']);

        $r2 = $this->synchro($jeton, $r1['revision'])->json();
        $this->assertCount(0, $r2['donnees']['produits']);

        $produit->update(['prix_vente' => 550]);
        $r3 = $this->synchro($jeton, $r2['revision'])->json();
        $this->assertSame(550, $r3['donnees']['produits'][0]['prix_vente']);

        $produit->delete();
        $r4 = $this->synchro($jeton, $r3['revision'])->json();
        $this->assertSame([['table_nom' => 'produits', 'uuid' => $produit->uuid]], $r4['suppressions']);
    }

    public function test_ventes_hors_ligne_de_deux_appareils_sans_doublon(): void
    {
        $gerant = User::factory()->gerant()->create();
        $vendeur = User::factory()->create();
        $produit = Produit::create(['nom' => 'Eau', 'prix_achat' => 200, 'prix_vente' => 400, 'unite' => 'pièce', 'seuil_alerte' => 1, 'actif' => true]);
        $produit->mouvementer(3, 'entree', $gerant->id);

        $tel1 = $this->connecter($vendeur);
        $tel2 = $this->connecter($gerant);

        // Le client est créé hors ligne sur le téléphone 1, en même temps que la vente à crédit
        $clientUuid = (string) Str::uuid();
        $vente1 = [
            'uuid' => (string) Str::uuid(), 'numero' => 'A1-000001', 'client_uuid' => $clientUuid,
            'montant_paye' => 400, 'mode_paiement' => 'especes', 'created_at' => now()->subHours(2)->toIso8601String(),
            'lignes' => [['uuid' => (string) Str::uuid(), 'produit_uuid' => $produit->uuid, 'quantite' => 2, 'prix_unitaire' => 400]],
        ];
        $changements = ['clients' => [['uuid' => $clientUuid, 'nom' => 'Awa']], 'ventes' => [$vente1]];

        $r = $this->synchro($tel1['jeton'], 0, $changements)->assertOk()->json();
        $this->assertSame([], $r['rejets']);
        // Renvoi après une coupure : pas de doublon
        $this->synchro($tel1['jeton'], 0, $changements)->assertOk();
        $this->assertSame(1, Vente::count());

        // Le téléphone 2 avait aussi vendu 2 bouteilles hors ligne : le stock passe à -1, la vente est gardée
        $vente2 = $vente1;
        $vente2['uuid'] = (string) Str::uuid();
        $vente2['numero'] = 'A2-000001';
        $vente2['client_uuid'] = null;
        $vente2['montant_paye'] = 800;
        $vente2['lignes'][0]['uuid'] = (string) Str::uuid();
        $r2 = $this->synchro($tel2['jeton'], 0, ['ventes' => [$vente2]])->json();

        $this->assertSame(-1, $produit->fresh()->stock);
        $this->assertSame(400, Client::where('uuid', $clientUuid)->first()->dette());
        $this->assertSame(now()->subHours(2)->format('Y-m-d H'), Vente::where('numero', 'A1-000001')->first()->created_at->format('Y-m-d H'));
        // Le téléphone 2 reçoit la vente du téléphone 1 et le client
        $this->assertCount(2, $r2['donnees']['ventes']);
        $this->assertSame('Awa', $r2['donnees']['clients'][0]['nom']);
    }

    public function test_le_vendeur_ne_peut_pas_modifier_les_produits_ni_les_depenses(): void
    {
        $vendeur = User::factory()->create();
        $jeton = $this->connecter($vendeur)['jeton'];

        $r = $this->synchro($jeton, 0, [
            'produits' => [['uuid' => (string) Str::uuid(), 'nom' => 'Pirate', 'unite' => 'p', 'prix_achat' => 0, 'prix_vente' => 1, 'seuil_alerte' => 0, 'actif' => true]],
            'depenses' => [['uuid' => (string) Str::uuid(), 'libelle' => 'x', 'categorie' => 'Autre', 'montant' => 5, 'date_depense' => today()->toDateString()]],
        ])->assertOk()->json();

        $this->assertSame(0, Produit::count());
        $this->assertArrayNotHasKey('depenses', $r['donnees']);
    }

    public function test_le_gerant_cree_produits_entrees_et_annule_hors_ligne(): void
    {
        $gerant = User::factory()->gerant()->create();
        $jeton = $this->connecter($gerant)['jeton'];
        $cat = (string) Str::uuid();
        $prod = (string) Str::uuid();
        $venteUuid = (string) Str::uuid();

        $r = $this->synchro($jeton, 0, [
            'categories' => [['uuid' => $cat, 'nom' => 'Boissons']],
            'produits' => [['uuid' => $prod, 'nom' => 'Jus', 'categorie_uuid' => $cat, 'unite' => 'pièce', 'prix_achat' => 200, 'prix_vente' => 350, 'seuil_alerte' => 2, 'actif' => true]],
            'mouvements' => [['uuid' => (string) Str::uuid(), 'produit_uuid' => $prod, 'type' => 'entree', 'quantite' => 12, 'prix_achat_unitaire' => 210]],
            'ventes' => [['uuid' => $venteUuid, 'montant_paye' => 350, 'lignes' => [['produit_uuid' => $prod, 'quantite' => 1, 'prix_unitaire' => 350]]]],
            'annulations' => [['vente_uuid' => $venteUuid, 'motif' => 'Erreur']],
        ])->assertOk()->json();

        $this->assertSame([], $r['rejets']);
        $produit = Produit::where('uuid', $prod)->first();
        $this->assertSame(12, $produit->stock);
        $this->assertSame(210, $produit->prix_achat);
        $this->assertSame('Boissons', $produit->categorie->nom);
        $this->assertSame('annulee', Vente::parUuid($venteUuid)->statut);
    }

    public function test_un_element_invalide_est_rejete_sans_bloquer_les_autres(): void
    {
        $vendeur = User::factory()->create();
        $jeton = $this->connecter($vendeur)['jeton'];

        $r = $this->synchro($jeton, 0, ['clients' => [
            ['uuid' => (string) Str::uuid(), 'nom' => ''],
            ['uuid' => (string) Str::uuid(), 'nom' => 'Valide'],
        ]])->assertOk()->json();

        $this->assertCount(1, $r['rejets']);
        $this->assertSame(1, Client::count());
    }

    public function test_reception_par_morceaux_sans_rien_perdre(): void
    {
        $gerant = User::factory()->gerant()->create();
        for ($i = 0; $i < 7; $i++) {
            Client::create(['nom' => "Client $i"]);
        }
        $jeton = $this->connecter($gerant)['jeton'];

        Synchronisation::$limite = 3;
        $recus = [];
        $appels = 0;
        $depuis = 0;
        do {
            $r = $this->synchro($jeton, $depuis)->json();
            $appels++;
            foreach ($r['donnees']['clients'] as $c) {
                $recus[] = $c['nom'];
            }
            $depuis = $r['revision'];
        } while ($r['encore']);

        Synchronisation::$limite = 500;
        $this->assertCount(7, array_unique($recus));
        $this->assertCount(7, $recus);
        $this->assertGreaterThan(2, $appels);
    }
}
