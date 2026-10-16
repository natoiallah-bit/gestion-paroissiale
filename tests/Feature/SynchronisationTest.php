<?php

namespace Tests\Feature;

use App\Models\Ceb;
use App\Models\Depot;
use App\Models\Fidele;
use App\Models\Inscription;
use App\Models\Sacrement;
use App\Models\Secteur;
use App\Models\User;
use App\Support\Enregistreur;
use App\Support\Ressources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SynchronisationTest extends TestCase
{
    use RefreshDatabase;

    private function connecter(User $user): array
    {
        return $this->postJson('/api/connexion', [
            'email' => $user->email, 'password' => 'password',
            'appareil_uuid' => (string) Str::uuid(), 'appareil_nom' => 'Téléphone test',
        ])->assertOk()->json();
    }

    private function synchro(string $jeton, int $depuis = 0, array $changements = [])
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($jeton)->postJson('/api/synchroniser', ['depuis' => $depuis, 'changements' => $changements]);
    }

    public function test_connexion_refuse_un_mauvais_mot_de_passe_et_attribue_un_code_appareil(): void
    {
        $user = User::factory()->admin()->create();
        $this->postJson('/api/connexion', ['email' => $user->email, 'password' => 'faux', 'appareil_uuid' => (string) Str::uuid(), 'appareil_nom' => 'x'])
            ->assertStatus(422);

        $this->assertSame('A1', $this->connecter($user)['appareil']['code']);
        $this->assertSame('A2', $this->connecter($user)['appareil']['code']);
        $this->withToken('jeton-invalide')->postJson('/api/synchroniser', ['depuis' => 0])->assertUnauthorized();
    }

    public function test_envoi_hors_ligne_puis_renvoi_sans_doublon(): void
    {
        $admin = User::factory()->admin()->create();
        $jeton = $this->connecter($admin)['jeton'];
        [$p, $c, $f, $s] = [(string) Str::uuid(), (string) Str::uuid(), (string) Str::uuid(), (string) Str::uuid()];

        $changements = [
            'secteurs' => [['uuid' => $p, 'nom' => 'Saint-Paul', 'localite' => 'Abidjan']],
            'cebs' => [['uuid' => $c, 'secteur_uuid' => $p, 'nom' => 'CEB Saint-Joseph']],
            'fideles' => [['uuid' => $f, 'secteur_uuid' => $p, 'ceb_uuid' => $c, 'nom' => 'Koné', 'prenoms' => 'Awa', 'sexe' => 'F', 'statut' => 'actif']],
            'sacrements' => [['uuid' => $s, 'fidele_uuid' => $f, 'type' => 'bapteme', 'date_sacrement' => '2001-04-12']],
        ];

        $r1 = $this->synchro($jeton, 0, $changements)->assertOk()->json();
        $this->assertSame([], $r1['rejets']);
        $this->assertSame([$s], $r1['acceptes']['sacrements']);
        $this->assertSame($c, $r1['donnees']['fideles'][0]['ceb_uuid']);
        $this->assertSame($p, $r1['donnees']['sacrements'][0]['secteur_uuid']);

        // Connexion coupée avant la réponse : l'appareil renvoie tout
        $r2 = $this->synchro($jeton, 0, $changements)->assertOk()->json();
        $this->assertSame([], $r2['rejets']);
        $this->assertSame(1, Secteur::count());
        $this->assertSame(1, Fidele::count());
        $this->assertSame(1, Sacrement::count());

        // Seuls les changements récents reviennent
        $r3 = $this->synchro($jeton, $r2['revision'])->assertOk()->json();
        $this->assertSame([], $r3['donnees']['fideles']);
    }

    public function test_une_modification_ne_renvoie_que_la_fiche_modifiee_et_les_suppressions(): void
    {
        $admin = User::factory()->admin()->create();
        $jeton = $this->connecter($admin)['jeton'];
        $p = Secteur::create(['nom' => 'A']);
        $ceb = Ceb::create(['secteur_id' => $p->id, 'nom' => 'CEB']);
        $revision = $this->synchro($jeton)->json('revision');

        $ceb->update(['responsable' => 'Mme Yao']);
        $r = $this->synchro($jeton, $revision)->json();
        $this->assertCount(1, $r['donnees']['cebs']);
        $this->assertSame('Mme Yao', $r['donnees']['cebs'][0]['responsable']);
        $this->assertCount(0, $r['donnees']['secteurs']);

        $uuid = $ceb->uuid;
        $ceb->delete();
        $r = $this->synchro($jeton, $r['revision'])->json();
        $this->assertSame([['table_nom' => 'cebs', 'uuid' => $uuid]], json_decode(json_encode($r['suppressions']), true));
    }

    public function test_elements_refuses_avec_la_raison(): void
    {
        $admin = User::factory()->admin()->create();
        $jeton = $this->connecter($admin)['jeton'];
        $p = Secteur::create(['nom' => 'A']);
        $f = Fidele::create(['secteur_id' => $p->id, 'nom' => 'X', 'prenoms' => 'Y', 'sexe' => 'M', 'statut' => 'actif']);
        $ok = (string) Str::uuid();
        $doublon = (string) Str::uuid();

        $r = $this->synchro($jeton, 0, ['sacrements' => [
            ['uuid' => $ok, 'fidele_uuid' => $f->uuid, 'type' => 'confirmation', 'date_sacrement' => '2010-01-01'],
            ['uuid' => $doublon, 'fidele_uuid' => $f->uuid, 'type' => 'confirmation', 'date_sacrement' => '2011-01-01'],
            ['uuid' => (string) Str::uuid(), 'fidele_uuid' => (string) Str::uuid(), 'type' => 'bapteme', 'date_sacrement' => '2011-01-01'],
            ['uuid' => 'pas-un-uuid', 'type' => 'bapteme'],
        ]])->assertOk()->json();

        $this->assertSame([$ok], $r['acceptes']['sacrements']);
        $this->assertCount(3, $r['rejets']);
        $this->assertStringContainsString('déjà enregistré', $r['rejets'][0]['raison']);
        $this->assertSame(1, Sacrement::count());
    }

    public function test_chaque_role_recoit_et_envoie_seulement_ce_qui_le_concerne(): void
    {
        $a = Secteur::create(['nom' => 'A']);
        $b = Secteur::create(['nom' => 'B']);
        Fidele::create(['secteur_id' => $a->id, 'nom' => 'ChezA', 'prenoms' => 'x', 'sexe' => 'M', 'statut' => 'actif']);
        Fidele::create(['secteur_id' => $b->id, 'nom' => 'ChezB', 'prenoms' => 'x', 'sexe' => 'M', 'statut' => 'actif']);

        $secretaire = User::factory()->create(['role' => 'secretaire', 'secteur_id' => $a->id]);
        $r = $this->synchro($this->connecter($secretaire)['jeton'])->json();
        $this->assertSame(['ChezA'], array_column($r['donnees']['fideles'], 'nom'));
        $this->assertSame(['A'], array_column($r['donnees']['secteurs'], 'nom'));
        $this->assertArrayNotHasKey('operations', $r['donnees']);

        // Un fidèle envoyé pour le secteur B est enregistré dans le secteur A
        $nouveau = (string) Str::uuid();
        $r = $this->synchro($this->connecter($secretaire)['jeton'], 0, ['fideles' => [
            ['uuid' => $nouveau, 'secteur_uuid' => $b->uuid, 'nom' => 'Nouveau', 'prenoms' => 'x', 'sexe' => 'F', 'statut' => 'actif'],
        ]])->json();
        $this->assertSame([], $r['rejets']);
        $this->assertSame($a->id, Fidele::where('uuid', $nouveau)->value('secteur_id'));

        // Le trésorier ne reçoit pas les fidèles et n'a pas le droit d'en envoyer
        $tresorier = User::factory()->create(['role' => 'tresorier', 'secteur_id' => $a->id]);
        $r = $this->synchro($this->connecter($tresorier)['jeton'], 0, ['fideles' => [
            ['uuid' => (string) Str::uuid(), 'secteur_uuid' => $a->uuid, 'nom' => 'Non', 'prenoms' => 'x', 'sexe' => 'F', 'statut' => 'actif'],
        ]])->json();
        $this->assertArrayNotHasKey('fideles', $r['donnees']);
        $this->assertCount(1, $r['rejets']);
        $this->assertSame(3, Fidele::count());
    }

    public function test_un_compte_desactive_ne_synchronise_plus(): void
    {
        $user = User::factory()->admin()->create();
        $jeton = $this->connecter($user)['jeton'];
        $user->update(['actif' => false]);
        $this->synchro($jeton)->assertForbidden();
    }

    public function test_depots_et_comptes_de_ceb_se_synchronisent_selon_le_role(): void
    {
        $a = Secteur::create(['nom' => 'A']);
        $b = Secteur::create(['nom' => 'B']);
        $cebA = Ceb::create(['secteur_id' => $a->id, 'nom' => 'CEB A']);
        $tresorierA = User::factory()->create(['role' => 'tresorier', 'secteur_id' => $a->id]);
        $central = User::factory()->create(['role' => 'tresorier']);
        $dB = (string) Str::uuid();

        $r = $this->synchro($this->connecter($central)['jeton'], 0, ['depots' => [
            ['uuid' => $dB, 'secteur_uuid' => $b->uuid, 'date_depot' => '2026-03-01', 'montant' => 7000],
        ]])->json();
        $this->assertSame([], $r['rejets']);

        $ope = (string) Str::uuid();
        $r = $this->synchro($this->connecter($tresorierA)['jeton'], 0, [
            'operations' => [['uuid' => $ope, 'ceb_uuid' => $cebA->uuid, 'sens' => 'recette', 'categorie' => 'Cotisation de CEB', 'libelle' => 'x', 'montant' => 12000, 'date_operation' => '2026-03-02']],
            'depots' => [['uuid' => (string) Str::uuid(), 'secteur_uuid' => $b->uuid, 'date_depot' => '2026-03-03', 'montant' => 1000]],
        ])->json();
        $this->assertSame([$ope], $r['acceptes']['operations']);
        $this->assertSame($a->uuid, $r['donnees']['operations'][0]['secteur_uuid']);
        // Le dépôt envoyé est enregistré dans son propre secteur, et le dépôt du secteur B reste invisible
        $this->assertSame([], array_filter($r['donnees']['depots'], fn ($d) => $d['uuid'] === $dB));
        $this->assertSame(0, Depot::where('secteur_id', $b->id)->where('montant', 1000)->count());
    }

    public function test_la_catechese_se_synchronise_avec_le_secteur_derive(): void
    {
        $secteur = Secteur::create(['nom' => 'A']);
        $fidele = Fidele::create(['secteur_id' => $secteur->id, 'nom' => 'X', 'prenoms' => 'Y', 'sexe' => 'F', 'statut' => 'actif']);
        $resp = User::factory()->create(['role' => 'catechese', 'secteur_id' => $secteur->id]);
        [$cat, $grp, $ins, $sea] = [(string) Str::uuid(), (string) Str::uuid(), (string) Str::uuid(), (string) Str::uuid()];
        $annee = Ressources::anneePastorale();

        $r = $this->synchro($this->connecter($resp)['jeton'], 0, [
            'catechistes' => [['uuid' => $cat, 'secteur_uuid' => $secteur->uuid, 'nom' => 'Yao', 'prenoms' => 'Marie', 'formation' => 'aucune', 'statut' => 'actif']],
            'groupes' => [['uuid' => $grp, 'secteur_uuid' => $secteur->uuid, 'annee' => $annee, 'nom' => 'G1', 'niveau' => 'bapteme', 'catechiste_uuid' => $cat]],
            'inscriptions' => [['uuid' => $ins, 'fidele_uuid' => $fidele->uuid, 'groupe_uuid' => $grp, 'date_inscription' => today()->toDateString(), 'statut' => 'inscrit']],
            'seances' => [['uuid' => $sea, 'groupe_uuid' => $grp, 'date_seance' => today()->toDateString(), 'theme' => 'T', 'presents' => 5, 'absents' => 1]],
        ])->json();

        $this->assertSame([], $r['rejets']);
        $this->assertSame($secteur->uuid, $r['donnees']['inscriptions'][0]['secteur_uuid']);
        $this->assertSame($secteur->uuid, $r['donnees']['seances'][0]['secteur_uuid']);
        $this->assertSame($grp, $r['donnees']['inscriptions'][0]['groupe_uuid']);

        // Le bapteme enregistré ensuite admet l'inscrit ; le trésorier ne reçoit rien de la catéchèse
        Enregistreur::enregistrer('sacrements', ['uuid' => (string) Str::uuid(), 'fidele_uuid' => $fidele->uuid, 'type' => 'bapteme', 'date_sacrement' => today()->toDateString()], User::factory()->create(['role' => 'secretaire', 'secteur_id' => $secteur->id]));
        $this->assertSame('admis', Inscription::first()->statut);

        $tresorier = User::factory()->create(['role' => 'tresorier', 'secteur_id' => $secteur->id]);
        $r = $this->synchro($this->connecter($tresorier)['jeton'])->json();
        $this->assertArrayNotHasKey('groupes', $r['donnees']);
        $r = $this->synchro($this->connecter($tresorier)['jeton'], 0, ['groupes' => [['uuid' => (string) Str::uuid(), 'secteur_uuid' => $secteur->uuid, 'annee' => $annee, 'nom' => 'Non', 'niveau' => 'eveil']]])->json();
        $this->assertCount(1, $r['rejets']);
    }
}
