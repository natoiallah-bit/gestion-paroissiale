<?php

namespace Tests\Feature;

use App\Models\Catechiste;
use App\Models\Ceb;
use App\Models\Depot;
use App\Models\Fidele;
use App\Models\GroupeCatechese;
use App\Models\Inscription;
use App\Models\Operation;
use App\Models\Sacrement;
use App\Models\Seance;
use App\Models\Secteur;
use App\Models\User;
use App\Support\Ressources;
use App\Support\Statistiques;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecteurTest extends TestCase
{
    use RefreshDatabase;

    private function secteur(string $nom = 'Saint-Paul'): Secteur
    {
        return Secteur::create(['nom' => $nom, 'responsable' => 'M. Ndolngar']);
    }

    private function fidele(Secteur $p, array $extra = []): Fidele
    {
        return Fidele::create($extra + ['secteur_id' => $p->id, 'nom' => 'Koné', 'prenoms' => 'Awa', 'sexe' => 'F', 'statut' => 'actif']);
    }

    private function secretaire(Secteur $p): User
    {
        return User::factory()->create(['role' => 'secretaire', 'secteur_id' => $p->id]);
    }

    public function test_les_pages_exigent_une_connexion(): void
    {
        $this->get('/fideles')->assertRedirect('/login');
    }

    public function test_droits_par_role(): void
    {
        $p = $this->secteur();
        $admin = User::factory()->admin()->create();
        $secretaire = $this->secretaire($p);
        $tresorier = User::factory()->create(['role' => 'tresorier', 'secteur_id' => $p->id]);

        $this->actingAs($admin)->get('/')->assertOk();
        $this->actingAs($admin)->get('/operations')->assertOk();
        $this->actingAs($admin)->get('/users')->assertOk();

        $this->actingAs($secretaire)->get('/fideles')->assertOk();
        $this->actingAs($secretaire)->get('/operations')->assertForbidden();
        $this->actingAs($secretaire)->get('/users')->assertForbidden();
        $this->actingAs($secretaire)->get('/secteurs/nouveau')->assertForbidden();

        $this->actingAs($tresorier)->get('/operations')->assertOk();
        $this->actingAs($tresorier)->get('/fideles')->assertForbidden();
        $this->actingAs($tresorier)->get('/sacrements')->assertForbidden();
    }

    public function test_un_secretaire_ne_voit_et_ne_modifie_que_son_secteur(): void
    {
        $a = $this->secteur('A');
        $b = $this->secteur('B');
        $chezB = $this->fidele($b, ['nom' => 'Autre']);
        $secretaire = $this->secretaire($a);

        $this->actingAs($secretaire)->get('/fideles/'.$chezB->id)->assertNotFound();
        $this->actingAs($secretaire)->get('/fideles')->assertDontSee('Autre');

        // Même si le formulaire désigne le secteur B, la fiche est créée dans le secteur A
        $this->actingAs($secretaire)->post('/fideles', [
            'secteur_uuid' => $b->uuid, 'nom' => 'Traoré', 'prenoms' => 'Moussa', 'sexe' => 'M', 'statut' => 'actif',
        ])->assertRedirect();
        $this->assertSame($a->id, Fidele::where('nom', 'Traoré')->value('secteur_id'));

        $this->actingAs($secretaire)->delete('/fideles/'.$chezB->id)->assertNotFound();
        $this->assertDatabaseHas('fideles', ['id' => $chezB->id]);
    }

    public function test_une_ceb_d_un_autre_secteur_est_refusee(): void
    {
        $a = $this->secteur('A');
        $b = $this->secteur('B');
        $cebB = Ceb::create(['secteur_id' => $b->id, 'nom' => 'CEB Sainte-Marie']);

        $this->actingAs($this->secretaire($a))->post('/fideles', [
            'nom' => 'Diallo', 'prenoms' => 'Fanta', 'sexe' => 'F', 'statut' => 'actif', 'ceb_uuid' => $cebB->uuid,
        ])->assertSessionHasErrors('ceb_uuid');
        $this->assertDatabaseMissing('fideles', ['nom' => 'Diallo']);
    }

    public function test_sacrements_unicite_obseques_et_extrait(): void
    {
        $p = $this->secteur();
        $f = $this->fidele($p, ['date_naissance' => '1990-05-01']);
        $secretaire = $this->secretaire($p);

        $donnees = ['fidele_uuid' => $f->uuid, 'type' => 'bapteme', 'date_sacrement' => '1990-07-15', 'lieu' => 'Cathédrale'];
        $this->actingAs($secretaire)->post('/sacrements', $donnees)->assertRedirect();
        $this->actingAs($secretaire)->post('/sacrements', $donnees)->assertSessionHasErrors('type');
        $this->assertSame(1, Sacrement::count());
        $this->assertSame($p->id, Sacrement::first()->secteur_id);

        // Avant la naissance : refusé
        $this->actingAs($secretaire)->post('/sacrements', ['fidele_uuid' => $f->uuid, 'type' => 'confirmation', 'date_sacrement' => '1980-01-01'])
            ->assertSessionHasErrors('date_sacrement');

        $this->actingAs($secretaire)->get('/sacrements/'.Sacrement::first()->id.'/extrait')->assertOk()->assertSee('KONÉ')->assertSee('Cathédrale');

        $this->actingAs($secretaire)->post('/sacrements', ['fidele_uuid' => $f->uuid, 'type' => 'obseques', 'date_sacrement' => today()->toDateString()])->assertRedirect();
        $this->assertSame('decede', $f->fresh()->statut);
    }

    public function test_suppression_protegee_et_detachement(): void
    {
        $p = $this->secteur();
        $ceb = Ceb::create(['secteur_id' => $p->id, 'nom' => 'CEB 1']);
        $f = $this->fidele($p, ['ceb_id' => $ceb->id]);
        $admin = User::factory()->admin()->create();

        // Un secteur non vide ne se supprime pas
        $this->actingAs($admin)->delete('/secteurs/'.$p->id)->assertSessionHasErrors('suppression');

        // Supprimer une CEB détache ses fidèles (et le signale aux appareils)
        $revision = $f->revision;
        $this->actingAs($admin)->delete('/cebs/'.$ceb->id)->assertRedirect();
        $this->assertNull($f->fresh()->ceb_id);
        $this->assertGreaterThan($revision, $f->fresh()->revision);
        $this->assertDatabaseHas('suppressions', ['table_nom' => 'cebs', 'uuid' => $ceb->uuid]);

        // Un fidèle ayant des sacrements ne se supprime pas
        Sacrement::create(['fidele_id' => $f->id, 'secteur_id' => $p->id, 'type' => 'bapteme', 'date_sacrement' => '2000-01-01']);
        $this->actingAs($admin)->delete('/fideles/'.$f->id)->assertSessionHasErrors('suppression');
    }

    public function test_finances_categories_coherentes_et_bilan(): void
    {
        $p = $this->secteur();
        $tresorier = User::factory()->create(['role' => 'tresorier', 'secteur_id' => $p->id]);
        $base = ['sens' => 'recette', 'categorie' => 'Quête', 'libelle' => 'Messe du dimanche', 'montant' => 25000, 'date_operation' => today()->toDateString()];

        $this->actingAs($tresorier)->post('/operations', $base)->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($tresorier)->post('/operations', ['sens' => 'depense', 'categorie' => 'Quête'] + $base)->assertSessionHasErrors('categorie');
        $this->actingAs($tresorier)->post('/operations', ['sens' => 'depense', 'categorie' => 'Transport', 'libelle' => 'Carburant', 'montant' => 10000] + $base)->assertRedirect();

        $this->actingAs($tresorier)->get('/')->assertOk()->assertSee('15 000 F');
        $this->actingAs($tresorier)->get('/rapports')->assertOk()->assertSee('Quête');
    }

    public function test_tableau_de_bord_du_vicariat(): void
    {
        $a = $this->secteur('A');
        $b = $this->secteur('B');
        $this->fidele($a);
        $this->fidele($b, ['sexe' => 'M']);
        $this->fidele($b, ['statut' => 'decede']);

        $this->actingAs(User::factory()->admin()->create())->get('/')->assertOk()->assertSee('1 hommes · 1 femmes');
        $this->actingAs($this->secretaire($a))->get('/')->assertOk()->assertSee('0 hommes · 1 femmes');
    }

    public function test_comptes_limites_a_un_secteur(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/users', [
            'name' => 'Sec', 'email' => 's@x.ci', 'role' => 'secretaire', 'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1',
        ])->assertSessionHasErrors('secteur_id');
    }

    public function test_comptes_des_ceb_compilation_du_secteur_et_depots_au_vicariat(): void
    {
        $a = $this->secteur('Secteur 1');
        $b = $this->secteur('Secteur 2');
        $cebA = Ceb::create(['secteur_id' => $a->id, 'nom' => 'CEB Saint-Joseph']);
        $cebB = Ceb::create(['secteur_id' => $b->id, 'nom' => 'CEB Sainte-Anne']);
        $tresorierA = User::factory()->create(['role' => 'tresorier', 'secteur_id' => $a->id]);
        $central = User::factory()->create(['role' => 'tresorier']); // sans secteur : conseil économique du vicariat
        $op = ['categorie' => 'Cotisation de CEB', 'libelle' => 'Cotisation', 'date_operation' => today()->toDateString(), 'sens' => 'recette'];

        // Le trésorier du secteur ouvre des comptes de CEB : le secteur est déduit de la CEB
        $this->actingAs($tresorierA)->post('/operations', $op + ['ceb_uuid' => $cebA->uuid, 'montant' => 30000])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($tresorierA)->post('/operations', ['sens' => 'depense', 'categorie' => 'Transport', 'montant' => 5000] + $op)->assertSessionHasNoErrors();
        $this->assertSame($a->id, Operation::where('montant', 30000)->value('secteur_id'));
        // ...mais jamais pour la CEB d'un autre secteur
        $this->actingAs($tresorierA)->post('/operations', $op + ['ceb_uuid' => $cebB->uuid, 'montant' => 1000])->assertSessionHasErrors('ceb_uuid');
        // Le secteur 2 dépose de l'argent, puis le vicariat en reçoit de chacun
        $this->actingAs($central)->post('/operations', $op + ['secteur_uuid' => $b->uuid, 'ceb_uuid' => $cebB->uuid, 'montant' => 20000])->assertSessionHasNoErrors();

        $depot = ['date_depot' => today()->subDays(2)->toDateString()];
        $this->actingAs($tresorierA)->post('/depots', $depot + ['montant' => 10000, 'reference' => 'R1'])->assertSessionHasNoErrors()
            ->assertSessionHas('succes', fn ($m) => str_contains($m, '10 000 F') && ! str_contains($m, 'Total reçu au vicariat'));
        $this->actingAs($central)->post('/depots', ['secteur_uuid' => $b->uuid, 'date_depot' => today()->subDay()->toDateString(), 'montant' => 20000])
            ->assertSessionHas('succes', fn ($m) => str_contains($m, 'Total reçu au vicariat : 30 000 F'));
        $this->actingAs($tresorierA)->post('/depots', ['date_depot' => today()->toDateString(), 'montant' => 5000]);

        // Les cumuls sont recalculés à chaque dépôt, dans l'ordre des dates
        $cumuls = Statistiques::cumulsDepots();
        $dernier = Depot::orderByDesc('date_depot')->first();
        $this->assertSame(['cumul_secteur' => 15000, 'total_vicariat' => 35000], $cumuls[$dernier->id]);

        // Compilation : 30 000 − 5 000 = 25 000 pour le secteur 1, dont 15 000 déjà déposés
        $c = Statistiques::comptes($central);
        $ligneA = collect($c['secteurs'])->firstWhere(fn ($l) => $l['secteur']->id === $a->id);
        $this->assertSame(25000, $ligneA['compile']['solde']);
        $this->assertSame(15000, $ligneA['depose']);
        $this->assertSame(10000, $ligneA['reste']);
        $this->assertSame(35000, $c['central']['recu']);

        // Un trésorier de secteur ne voit ni les autres secteurs, ni la caisse centrale, ni le total du vicariat
        $this->actingAs($tresorierA)->get('/depots')->assertOk()->assertSee('R1')->assertDontSee('Total au vicariat');
        $vue = Statistiques::comptes($tresorierA);
        $this->assertCount(1, $vue['secteurs']);
        $this->assertNull($vue['central']);
        $this->actingAs($central)->get('/depots')->assertOk()->assertSee('Total au vicariat');
        $this->actingAs($tresorierA)->get('/rapports')->assertOk()->assertSee('Compilation du secteur');
    }

    public function test_la_caisse_centrale_est_reservee_au_vicariat(): void
    {
        $a = $this->secteur('Secteur 1');
        $tresorierA = User::factory()->create(['role' => 'tresorier', 'secteur_id' => $a->id]);
        $central = User::factory()->create(['role' => 'tresorier']);
        $op = ['sens' => 'depense', 'categorie' => 'Transport', 'libelle' => 'Carburant', 'montant' => 4000, 'date_operation' => today()->toDateString()];

        // Sans secteur dans le formulaire : un trésorier de secteur écrit quand même dans son secteur
        $this->actingAs($tresorierA)->post('/operations', $op)->assertSessionHasNoErrors();
        $this->assertSame($a->id, Operation::first()->secteur_id);

        $this->actingAs($central)->post('/operations', $op)->assertSessionHasNoErrors();
        $centrale = Operation::whereNull('secteur_id')->first();
        $this->assertNotNull($centrale);

        // Le secteur ne voit ni ne modifie la caisse centrale
        $this->actingAs($tresorierA)->get('/operations/'.$centrale->id)->assertNotFound();
        $this->actingAs($tresorierA)->delete('/operations/'.$centrale->id)->assertNotFound();
        $this->assertDatabaseHas('operations', ['id' => $centrale->id]);
    }

    /** Un secteur avec un fidèle actif, un catéchiste et un groupe de catéchèse (année en cours) */
    private function catechese(string $niveau = 'communion'): array
    {
        $s = $this->secteur('Secteur C');
        $fidele = $this->fidele($s, ['nom' => 'Ngarbaye', 'prenoms' => 'Esther', 'date_naissance' => '2014-03-02']);
        $catechiste = Catechiste::create(['secteur_id' => $s->id, 'nom' => 'Yao', 'prenoms' => 'Marie', 'formation' => 'initiale', 'statut' => 'actif']);
        $groupe = GroupeCatechese::create([
            'secteur_id' => $s->id, 'catechiste_id' => $catechiste->id, 'annee' => Ressources::anneePastorale(), 'nom' => 'Groupe Saint-Pierre', 'niveau' => $niveau,
        ]);

        return [$s, $fidele, $catechiste, $groupe, User::factory()->create(['role' => 'catechese', 'secteur_id' => $s->id])];
    }

    public function test_catechese_inscription_un_seul_groupe_par_annee_et_meme_secteur(): void
    {
        [$s, $fidele, , $groupe, $resp] = $this->catechese();
        $autreGroupe = GroupeCatechese::create(['secteur_id' => $s->id, 'annee' => $groupe->annee, 'nom' => 'Groupe B', 'niveau' => 'eveil']);
        $autre = $this->secteur('Autre secteur');
        $groupeAutre = GroupeCatechese::create(['secteur_id' => $autre->id, 'annee' => $groupe->annee, 'nom' => 'Groupe X', 'niveau' => 'eveil']);
        $donnees = ['fidele_uuid' => $fidele->uuid, 'groupe_uuid' => $groupe->uuid, 'date_inscription' => today()->toDateString(), 'statut' => 'inscrit'];

        $this->actingAs($resp)->post('/inscriptions', $donnees)->assertSessionHasNoErrors();
        $this->assertSame($s->id, Inscription::first()->secteur_id);
        // Même année : pas dans un deuxième groupe
        $this->actingAs($resp)->post('/inscriptions', ['groupe_uuid' => $autreGroupe->uuid] + $donnees)->assertSessionHasErrors('groupe_uuid');
        // Groupe d'un autre secteur
        $this->actingAs($resp)->post('/inscriptions', ['groupe_uuid' => $groupeAutre->uuid] + $donnees)->assertSessionHasErrors();
        $this->assertSame(1, Inscription::count());

        // Un fidèle décédé ne s'inscrit pas
        $mort = $this->fidele($s, ['statut' => 'decede', 'nom' => 'Defunt']);
        $this->actingAs($resp)->post('/inscriptions', ['fidele_uuid' => $mort->uuid, 'groupe_uuid' => $autreGroupe->uuid] + $donnees)->assertSessionHasErrors('fidele_uuid');
    }

    public function test_annee_pastorale_et_catechiste_du_meme_secteur(): void
    {
        [$s, , $catechiste, , $resp] = $this->catechese();
        $base = ['nom' => 'Groupe Z', 'niveau' => 'eveil'];

        $this->actingAs($resp)->post('/groupes', $base + ['annee' => '2026-2028'])->assertSessionHasErrors('annee');
        $this->actingAs($resp)->post('/groupes', $base + ['annee' => '2026'])->assertSessionHasErrors('annee');
        $this->actingAs($resp)->post('/groupes', $base + ['annee' => '2026-2027', 'catechiste_uuid' => $catechiste->uuid])->assertSessionHasNoErrors();

        $autre = Catechiste::create(['secteur_id' => $this->secteur('Y')->id, 'nom' => 'Z', 'prenoms' => 'Z', 'statut' => 'actif']);
        $this->actingAs($resp)->post('/groupes', $base + ['annee' => '2026-2027', 'catechiste_uuid' => $autre->uuid])->assertSessionHasErrors('catechiste_uuid');

        $this->assertSame('2026-2027', Ressources::anneePastorale(new \DateTime('2026-10-14')));
        $this->assertSame('2025-2026', Ressources::anneePastorale(new \DateTime('2026-06-01')));
    }

    public function test_le_sacrement_recu_admet_l_inscrit_au_niveau_correspondant(): void
    {
        [$s, $fidele, , $groupe, $resp] = $this->catechese('communion');
        $this->actingAs($resp)->post('/inscriptions', ['fidele_uuid' => $fidele->uuid, 'groupe_uuid' => $groupe->uuid, 'date_inscription' => today()->toDateString(), 'statut' => 'inscrit']);
        $secretaire = $this->secretaire($s);

        // Un baptême ne valide pas une préparation à la première communion...
        $this->actingAs($secretaire)->post('/sacrements', ['fidele_uuid' => $fidele->uuid, 'type' => 'bapteme', 'date_sacrement' => '2014-06-01']);
        $this->assertSame('inscrit', Inscription::first()->statut);
        // ... la première communion, oui
        $this->actingAs($secretaire)->post('/sacrements', ['fidele_uuid' => $fidele->uuid, 'type' => 'eucharistie', 'date_sacrement' => today()->toDateString()]);
        $this->assertSame('admis', Inscription::first()->statut);
    }

    public function test_l_inscription_saisie_apres_le_sacrement_est_deja_admise(): void
    {
        [$s, $fidele, , $groupe, $resp] = $this->catechese('communion');
        $this->actingAs($this->secretaire($s))->post('/sacrements', ['fidele_uuid' => $fidele->uuid, 'type' => 'eucharistie', 'date_sacrement' => today()->toDateString()]);
        $this->actingAs($resp)->post('/inscriptions', ['fidele_uuid' => $fidele->uuid, 'groupe_uuid' => $groupe->uuid, 'date_inscription' => today()->subDays(3)->toDateString(), 'statut' => 'inscrit']);
        $this->assertSame('admis', Inscription::first()->statut);
    }

    public function test_seances_presence_et_statistiques(): void
    {
        [$s, $fidele, $catechiste, $groupe, $resp] = $this->catechese();
        $this->actingAs($resp)->post('/inscriptions', ['fidele_uuid' => $fidele->uuid, 'groupe_uuid' => $groupe->uuid, 'date_inscription' => today()->toDateString(), 'statut' => 'inscrit']);
        $seance = ['groupe_uuid' => $groupe->uuid, 'catechiste_uuid' => $catechiste->uuid, 'theme' => 'La prière', 'presents' => 12, 'absents' => 4];

        $this->actingAs($resp)->post('/seances', $seance + ['date_seance' => today()->toDateString()])->assertSessionHasNoErrors();
        $this->actingAs($resp)->post('/seances', ['theme' => 'Le baptême', 'presents' => 8, 'absents' => 0, 'date_seance' => today()->subDay()->toDateString()] + $seance)->assertSessionHasNoErrors();
        $this->actingAs($resp)->post('/seances', $seance + ['date_seance' => today()->addDays(5)->toDateString()])->assertSessionHasErrors('date_seance');
        $this->assertSame($s->id, Seance::first()->secteur_id);

        $c = Statistiques::catechese($resp);
        $this->assertSame(1, $c['groupes']);
        $this->assertSame(1, $c['catechistes']);
        $this->assertSame(1, $c['inscrits']);
        $this->assertSame(2, $c['seances']);
        $this->assertSame(83, $c['presence']); // 20 présents sur 24
        $this->assertSame(1, $c['par_niveau']['communion']['inscrit']);
        $this->assertNull($c['par_secteur']); // un compte de secteur ne voit pas les autres

        $this->actingAs($resp)->get('/')->assertOk()->assertSee('Catéchèse')->assertSee('présence moyenne 83 %');
        $this->actingAs($resp)->get('/rapports')->assertOk()->assertSee('Préparation à la première communion');
    }

    public function test_droits_et_portee_de_la_catechese(): void
    {
        [$s, $fidele, , $groupe, $resp] = $this->catechese();
        $autre = $this->secteur('Autre');
        $groupeAutre = GroupeCatechese::create(['secteur_id' => $autre->id, 'annee' => $groupe->annee, 'nom' => 'Chez eux', 'niveau' => 'eveil']);
        $tresorier = User::factory()->create(['role' => 'tresorier', 'secteur_id' => $s->id]);
        $coordination = User::factory()->create(['role' => 'catechese']); // sans secteur : tout le vicariat

        // Le trésorier n'a pas accès à la catéchèse ; le responsable ne voit pas les finances
        $this->actingAs($tresorier)->get('/groupes')->assertForbidden();
        $this->actingAs($resp)->get('/operations')->assertForbidden();
        // Le responsable de secteur ne voit que son secteur
        $this->actingAs($resp)->get('/groupes')->assertOk()->assertSee('Groupe Saint-Pierre')->assertDontSee('Chez eux');
        $this->actingAs($resp)->get('/groupes/'.$groupeAutre->id)->assertNotFound();
        // Mais la coordination vicariale voit tout, et ne modifie pas les fiches pastorales
        $this->actingAs($coordination)->get('/groupes')->assertOk()->assertSee('Chez eux')->assertSee('Groupe Saint-Pierre');
        $this->actingAs($coordination)->post('/fideles', ['nom' => 'X', 'prenoms' => 'Y', 'sexe' => 'M', 'statut' => 'actif', 'secteur_uuid' => $s->uuid])->assertForbidden();

        // Suppressions protégées
        $this->actingAs($resp)->post('/inscriptions', ['fidele_uuid' => $fidele->uuid, 'groupe_uuid' => $groupe->uuid, 'date_inscription' => today()->toDateString(), 'statut' => 'inscrit']);
        $this->actingAs($resp)->delete('/groupes/'.$groupe->id)->assertSessionHasErrors('suppression');
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->delete('/fideles/'.$fidele->id)->assertSessionHasErrors('suppression');
        $catechiste = Catechiste::first();
        $this->actingAs($resp)->delete('/catechistes/'.$catechiste->id)->assertRedirect();
        $this->assertNull($groupe->fresh()->catechiste_id); // le groupe est détaché proprement
    }

    public function test_les_pages_de_la_catechese_s_affichent(): void
    {
        [, $fidele, $catechiste, $groupe, $resp] = $this->catechese();
        $inscription = Inscription::create(['fidele_id' => $fidele->id, 'groupe_id' => $groupe->id, 'secteur_id' => $groupe->secteur_id, 'date_inscription' => today()->toDateString(), 'statut' => 'inscrit']);
        $seance = Seance::create(['groupe_id' => $groupe->id, 'secteur_id' => $groupe->secteur_id, 'date_seance' => today()->toDateString(), 'theme' => 'Thème', 'presents' => 3, 'absents' => 1]);

        foreach (['catechistes' => $catechiste, 'groupes' => $groupe, 'inscriptions' => $inscription, 'seances' => $seance] as $cle => $fiche) {
            $this->actingAs($resp)->get("/$cle")->assertOk();
            $this->actingAs($resp)->get("/$cle/nouveau")->assertOk();
            $this->actingAs($resp)->get("/$cle/{$fiche->id}")->assertOk();
            $this->actingAs($resp)->get("/$cle/{$fiche->id}/modifier")->assertOk();
        }
        $this->actingAs($resp)->get('/inscriptions?q=Ngarbaye')->assertOk()->assertSee('Ngarbaye');
        $this->actingAs($resp)->get('/groupes?annee='.$groupe->annee)->assertOk()->assertSee('Groupe Saint-Pierre');
        $this->actingAs($resp)->get('/fideles/'.$fidele->id)->assertOk()->assertSee('Catéchèse');
    }

    public function test_la_description_des_fiches_de_l_application_est_a_jour(): void
    {
        $this->assertSame(
            Ressources::pourApplication(),
            file_get_contents(base_path('application/src/ressources.json')),
            'Lancez : php artisan vicariat:ressources',
        );
    }
}
