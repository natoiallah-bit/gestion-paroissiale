<?php

namespace Tests\Feature;

use App\Models\Ceb;
use App\Models\Fidele;
use App\Models\Paroisse;
use App\Models\Sacrement;
use App\Models\User;
use App\Support\Ressources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParoisseTest extends TestCase
{
    use RefreshDatabase;

    private function paroisse(string $nom = 'Saint-Paul'): Paroisse
    {
        return Paroisse::create(['nom' => $nom, 'localite' => 'Abidjan']);
    }

    private function fidele(Paroisse $p, array $extra = []): Fidele
    {
        return Fidele::create($extra + ['paroisse_id' => $p->id, 'nom' => 'Koné', 'prenoms' => 'Awa', 'sexe' => 'F', 'statut' => 'actif']);
    }

    private function secretaire(Paroisse $p): User
    {
        return User::factory()->create(['role' => 'secretaire', 'paroisse_id' => $p->id]);
    }

    public function test_les_pages_exigent_une_connexion(): void
    {
        $this->get('/fideles')->assertRedirect('/login');
    }

    public function test_droits_par_role(): void
    {
        $p = $this->paroisse();
        $admin = User::factory()->admin()->create();
        $secretaire = $this->secretaire($p);
        $tresorier = User::factory()->create(['role' => 'tresorier', 'paroisse_id' => $p->id]);

        $this->actingAs($admin)->get('/')->assertOk();
        $this->actingAs($admin)->get('/operations')->assertOk();
        $this->actingAs($admin)->get('/users')->assertOk();

        $this->actingAs($secretaire)->get('/fideles')->assertOk();
        $this->actingAs($secretaire)->get('/operations')->assertForbidden();
        $this->actingAs($secretaire)->get('/users')->assertForbidden();
        $this->actingAs($secretaire)->get('/paroisses/nouveau')->assertForbidden();

        $this->actingAs($tresorier)->get('/operations')->assertOk();
        $this->actingAs($tresorier)->get('/fideles')->assertForbidden();
        $this->actingAs($tresorier)->get('/sacrements')->assertForbidden();
    }

    public function test_un_secretaire_ne_voit_et_ne_modifie_que_sa_paroisse(): void
    {
        $a = $this->paroisse('A');
        $b = $this->paroisse('B');
        $chezB = $this->fidele($b, ['nom' => 'Autre']);
        $secretaire = $this->secretaire($a);

        $this->actingAs($secretaire)->get('/fideles/'.$chezB->id)->assertNotFound();
        $this->actingAs($secretaire)->get('/fideles')->assertDontSee('Autre');

        // Même si le formulaire désigne la paroisse B, la fiche est créée dans la paroisse A
        $this->actingAs($secretaire)->post('/fideles', [
            'paroisse_uuid' => $b->uuid, 'nom' => 'Traoré', 'prenoms' => 'Moussa', 'sexe' => 'M', 'statut' => 'actif',
        ])->assertRedirect();
        $this->assertSame($a->id, Fidele::where('nom', 'Traoré')->value('paroisse_id'));

        $this->actingAs($secretaire)->delete('/fideles/'.$chezB->id)->assertNotFound();
        $this->assertDatabaseHas('fideles', ['id' => $chezB->id]);
    }

    public function test_une_ceb_d_une_autre_paroisse_est_refusee(): void
    {
        $a = $this->paroisse('A');
        $b = $this->paroisse('B');
        $cebB = Ceb::create(['paroisse_id' => $b->id, 'nom' => 'CEB Sainte-Marie']);

        $this->actingAs($this->secretaire($a))->post('/fideles', [
            'nom' => 'Diallo', 'prenoms' => 'Fanta', 'sexe' => 'F', 'statut' => 'actif', 'ceb_uuid' => $cebB->uuid,
        ])->assertSessionHasErrors('ceb_uuid');
        $this->assertDatabaseMissing('fideles', ['nom' => 'Diallo']);
    }

    public function test_sacrements_unicite_obseques_et_extrait(): void
    {
        $p = $this->paroisse();
        $f = $this->fidele($p, ['date_naissance' => '1990-05-01']);
        $secretaire = $this->secretaire($p);

        $donnees = ['fidele_uuid' => $f->uuid, 'type' => 'bapteme', 'date_sacrement' => '1990-07-15', 'lieu' => 'Cathédrale'];
        $this->actingAs($secretaire)->post('/sacrements', $donnees)->assertRedirect();
        $this->actingAs($secretaire)->post('/sacrements', $donnees)->assertSessionHasErrors('type');
        $this->assertSame(1, Sacrement::count());
        $this->assertSame($p->id, Sacrement::first()->paroisse_id);

        // Avant la naissance : refusé
        $this->actingAs($secretaire)->post('/sacrements', ['fidele_uuid' => $f->uuid, 'type' => 'confirmation', 'date_sacrement' => '1980-01-01'])
            ->assertSessionHasErrors('date_sacrement');

        $this->actingAs($secretaire)->get('/sacrements/'.Sacrement::first()->id.'/extrait')->assertOk()->assertSee('KONÉ')->assertSee('Cathédrale');

        $this->actingAs($secretaire)->post('/sacrements', ['fidele_uuid' => $f->uuid, 'type' => 'obseques', 'date_sacrement' => today()->toDateString()])->assertRedirect();
        $this->assertSame('decede', $f->fresh()->statut);
    }

    public function test_suppression_protegee_et_detachement(): void
    {
        $p = $this->paroisse();
        $ceb = Ceb::create(['paroisse_id' => $p->id, 'nom' => 'CEB 1']);
        $f = $this->fidele($p, ['ceb_id' => $ceb->id]);
        $admin = User::factory()->admin()->create();

        // Une paroisse non vide ne se supprime pas
        $this->actingAs($admin)->delete('/paroisses/'.$p->id)->assertSessionHasErrors('suppression');

        // Supprimer une CEB détache ses fidèles (et le signale aux appareils)
        $revision = $f->revision;
        $this->actingAs($admin)->delete('/cebs/'.$ceb->id)->assertRedirect();
        $this->assertNull($f->fresh()->ceb_id);
        $this->assertGreaterThan($revision, $f->fresh()->revision);
        $this->assertDatabaseHas('suppressions', ['table_nom' => 'cebs', 'uuid' => $ceb->uuid]);

        // Un fidèle ayant des sacrements ne se supprime pas
        Sacrement::create(['fidele_id' => $f->id, 'paroisse_id' => $p->id, 'type' => 'bapteme', 'date_sacrement' => '2000-01-01']);
        $this->actingAs($admin)->delete('/fideles/'.$f->id)->assertSessionHasErrors('suppression');
    }

    public function test_finances_categories_coherentes_et_bilan(): void
    {
        $p = $this->paroisse();
        $tresorier = User::factory()->create(['role' => 'tresorier', 'paroisse_id' => $p->id]);
        $base = ['sens' => 'recette', 'categorie' => 'Quête', 'libelle' => 'Messe du dimanche', 'montant' => 25000, 'date_operation' => today()->toDateString()];

        $this->actingAs($tresorier)->post('/operations', $base)->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($tresorier)->post('/operations', ['sens' => 'depense', 'categorie' => 'Quête'] + $base)->assertSessionHasErrors('categorie');
        $this->actingAs($tresorier)->post('/operations', ['sens' => 'depense', 'categorie' => 'Transport', 'libelle' => 'Carburant', 'montant' => 10000] + $base)->assertRedirect();

        $this->actingAs($tresorier)->get('/')->assertOk()->assertSee('15 000 F');
        $this->actingAs($tresorier)->get('/rapports')->assertOk()->assertSee('Quête');
    }

    public function test_tableau_de_bord_du_vicariat(): void
    {
        $a = $this->paroisse('A');
        $b = $this->paroisse('B');
        $this->fidele($a);
        $this->fidele($b, ['sexe' => 'M']);
        $this->fidele($b, ['statut' => 'decede']);

        $this->actingAs(User::factory()->admin()->create())->get('/')->assertOk()->assertSee('1 hommes · 1 femmes');
        $this->actingAs($this->secretaire($a))->get('/')->assertOk()->assertSee('0 hommes · 1 femmes');
    }

    public function test_comptes_limites_a_une_paroisse(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/users', [
            'name' => 'Sec', 'email' => 's@x.ci', 'role' => 'secretaire', 'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1',
        ])->assertSessionHasErrors('paroisse_id');
    }

    public function test_la_description_des_fiches_de_l_application_est_a_jour(): void
    {
        $this->assertSame(
            Ressources::pourApplication(),
            file_get_contents(base_path('application/src/ressources.json')),
            'Lancez : php artisan paroisse:ressources',
        );
    }
}
