<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Depense;
use App\Models\Produit;
use App\Models\User;
use App\Models\Vente;
use App\Support\Bilan;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommerceTest extends TestCase
{
    use RefreshDatabase;

    private User $gerant;

    private User $vendeur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gerant = User::factory()->gerant()->create();
        $this->vendeur = User::factory()->create();
    }

    private function produit(int $stock = 10, int $achat = 300, int $vente = 500): Produit
    {
        $p = Produit::create(['nom' => 'Produit '.uniqid(), 'prix_achat' => $achat, 'prix_vente' => $vente, 'unite' => 'pièce', 'seuil_alerte' => 2, 'actif' => true]);
        if ($stock) {
            $p->mouvementer($stock, 'entree', $this->gerant->id);
        }

        return $p->fresh();
    }

    public function test_une_vente_au_comptant_decremente_le_stock_et_utilise_le_prix_en_base(): void
    {
        $p = $this->produit(10);

        $this->actingAs($this->vendeur)->post(route('caisse.store'), [
            'lignes' => [['produit_id' => $p->id, 'quantite' => 3]],
            'montant_paye' => 5000, // le client donne plus : la monnaie n'est pas encaissée
            'mode_paiement' => 'especes',
            'prix' => 1, // ignoré
        ])->assertRedirect();

        $vente = Vente::first();
        $this->assertSame(1500, $vente->total);
        $this->assertSame(1500, $vente->montant_paye);
        $this->assertSame('V000001', $vente->numero);
        $this->assertSame(7, $p->fresh()->stock);
        $this->assertSame(-3, $p->mouvements()->where('type', 'vente')->value('quantite'));
    }

    public function test_stock_insuffisant_refuse_la_vente(): void
    {
        $p = $this->produit(2);

        $this->actingAs($this->vendeur)->post(route('caisse.store'), [
            'lignes' => [['produit_id' => $p->id, 'quantite' => 1], ['produit_id' => $p->id, 'quantite' => 2]],
            'montant_paye' => 1500,
            'mode_paiement' => 'especes',
        ])->assertSessionHasErrors('lignes');

        $this->assertSame(0, Vente::count());
        $this->assertSame(2, $p->fresh()->stock);
    }

    public function test_vente_a_credit_exige_un_client_puis_cree_une_dette(): void
    {
        $p = $this->produit(10);
        $donnees = ['lignes' => [['produit_id' => $p->id, 'quantite' => 4]], 'montant_paye' => 500, 'mode_paiement' => 'especes'];

        $this->actingAs($this->vendeur)->post(route('caisse.store'), $donnees)->assertSessionHasErrors('client_id');
        $this->assertSame(10, $p->fresh()->stock);

        $client = Client::create(['nom' => 'Awa']);
        $this->actingAs($this->vendeur)->post(route('caisse.store'), $donnees + ['client_id' => $client->id])->assertRedirect();
        $this->assertSame(1500, $client->dette());

        // Remboursement partiel puis refus d'un montant supérieur à la dette
        $this->actingAs($this->vendeur)->post(route('clients.rembourser', $client), ['montant' => 1000, 'mode_paiement' => 'mobile_money'])->assertRedirect();
        $this->assertSame(500, $client->dette());
        $this->actingAs($this->vendeur)->post(route('clients.rembourser', $client), ['montant' => 600, 'mode_paiement' => 'especes'])->assertSessionHasErrors('montant');

        $this->assertSame(500, (int) Client::query()->select('clients.*')->avecDette()->find($client->id)->dette);
    }

    public function test_annulation_remet_le_stock_et_efface_le_credit(): void
    {
        $p = $this->produit(10);
        $client = Client::create(['nom' => 'Moussa']);
        $vente = Vente::enregistrer([['produit_id' => $p->id, 'quantite' => 2]], 0, 'especes', $client->id, $this->vendeur->id);
        $this->assertSame(1000, $client->dette());

        $this->actingAs($this->vendeur)->post(route('ventes.annuler', $vente), ['motif' => 'test'])->assertForbidden();

        $this->actingAs($this->gerant)->post(route('ventes.annuler', $vente), ['motif' => 'Erreur'])->assertRedirect();
        $this->assertSame('annulee', $vente->fresh()->statut);
        $this->assertSame(10, $p->fresh()->stock);
        $this->assertSame(0, $client->dette());

        // Une deuxième annulation ne remet pas le stock une seconde fois
        $this->actingAs($this->gerant)->post(route('ventes.annuler', $vente), ['motif' => 'Encore']);
        $this->assertSame(10, $p->fresh()->stock);
    }

    public function test_bilan_calcule_marge_et_benefice_sans_compter_deux_fois_les_achats(): void
    {
        $p = $this->produit(10, 300, 500);
        Vente::enregistrer([['produit_id' => $p->id, 'quantite' => 4]], 2000, 'especes', null, $this->vendeur->id);
        Depense::create(['libelle' => 'Loyer', 'categorie' => 'Loyer', 'montant' => 300, 'date_depense' => today(), 'user_id' => $this->gerant->id]);
        Depense::create(['libelle' => 'Stock', 'categorie' => Depense::ACHAT_MARCHANDISE, 'montant' => 3000, 'date_depense' => today(), 'user_id' => $this->gerant->id]);

        $bilan = Bilan::periode(today(), today());

        $this->assertSame(2000, $bilan['chiffre_affaires']);
        $this->assertSame(1200, $bilan['cout_achats']);
        $this->assertSame(800, $bilan['marge']);
        $this->assertSame(300, $bilan['depenses']);
        $this->assertSame(3000, $bilan['achats_marchandise']);
        $this->assertSame(500, $bilan['benefice']);
    }

    public function test_entree_de_stock_et_ajustement(): void
    {
        $p = $this->produit(0, 300);

        $this->actingAs($this->gerant)->post(route('stock.entree.store'), [
            'lignes' => [['produit_id' => $p->id, 'quantite' => 12, 'prix_achat' => 350]],
            'maj_prix_achat' => 1,
            'enregistrer_depense' => 1,
        ])->assertRedirect();

        $p->refresh();
        $this->assertSame(12, $p->stock);
        $this->assertSame(350, $p->prix_achat);
        $this->assertSame(4200, (int) Depense::sum('montant'));

        $this->actingAs($this->gerant)->post(route('stock.ajuster', $p), ['stock_reel' => 9, 'note' => 'Casse'])->assertRedirect();
        $this->assertSame(9, $p->fresh()->stock);
        $this->assertSame(-3, $p->mouvements()->where('type', 'ajustement')->value('quantite'));
    }

    public function test_le_vendeur_n_a_pas_acces_a_la_gestion(): void
    {
        $this->actingAs($this->vendeur)->get(route('dashboard'))->assertRedirect(route('caisse.index'));
        foreach (['produits.index', 'depenses.index', 'rapports.index', 'users.index', 'parametres.edit', 'stock.entree'] as $route) {
            $this->actingAs($this->vendeur)->get(route($route))->assertForbidden();
        }

        $autre = Vente::enregistrer([['produit_id' => $this->produit()->id, 'quantite' => 1]], 500, 'especes', null, $this->gerant->id);
        $this->actingAs($this->vendeur)->get(route('ventes.show', $autre))->assertForbidden();
    }

    public function test_toutes_les_pages_s_affichent(): void
    {
        $this->seed(DemoSeeder::class);
        $gerant = User::where('role', 'gerant')->first();
        $vente = Vente::first();
        $produit = Produit::first();
        $client = Client::first();

        $pages = [
            route('dashboard'), route('caisse.index'), route('ventes.index'), route('ventes.show', $vente), route('ventes.ticket', $vente),
            route('clients.index'), route('clients.create'), route('clients.show', $client), route('clients.edit', $client),
            route('produits.index'), route('produits.create'), route('produits.show', $produit), route('produits.edit', $produit),
            route('stock.entree'), route('stock.mouvements'), route('categories.index'), route('fournisseurs.index'), route('fournisseurs.create'),
            route('depenses.index'), route('rapports.index'), route('users.index'), route('users.create'), route('parametres.edit'), route('appareils.index'),
        ];
        foreach ($pages as $url) {
            $this->actingAs($gerant)->get($url)->assertOk();
        }

        $this->actingAs($this->vendeur)->get(route('caisse.index'))->assertOk()->assertSee('Valider la vente');
    }

    public function test_connexion_et_compte_desactive(): void
    {
        $this->post(route('login.submit'), ['email' => $this->vendeur->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->vendeur);

        $this->vendeur->update(['actif' => false]);
        $this->app['auth']->forgetGuards(); // comme à une nouvelle requête : le compte est relu en base
        $this->get(route('caisse.index'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->post(route('login.submit'), ['email' => $this->vendeur->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_un_produit_deja_vendu_est_desactive_au_lieu_d_etre_supprime(): void
    {
        $p = $this->produit();
        Vente::enregistrer([['produit_id' => $p->id, 'quantite' => 1]], 500, 'especes', null, $this->gerant->id);

        $this->actingAs($this->gerant)->delete(route('produits.destroy', $p))->assertRedirect();
        $this->assertFalse($p->fresh()->actif);
    }
}
