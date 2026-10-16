<?php

namespace Tests\Feature;

use App\Models\Ceb;
use App\Models\Fidele;
use App\Models\Paroisse;
use App\Models\Sacrement;
use App\Models\User;
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
            'paroisses' => [['uuid' => $p, 'nom' => 'Saint-Paul', 'localite' => 'Abidjan']],
            'cebs' => [['uuid' => $c, 'paroisse_uuid' => $p, 'nom' => 'CEB Saint-Joseph']],
            'fideles' => [['uuid' => $f, 'paroisse_uuid' => $p, 'ceb_uuid' => $c, 'nom' => 'Koné', 'prenoms' => 'Awa', 'sexe' => 'F', 'statut' => 'actif']],
            'sacrements' => [['uuid' => $s, 'fidele_uuid' => $f, 'type' => 'bapteme', 'date_sacrement' => '2001-04-12']],
        ];

        $r1 = $this->synchro($jeton, 0, $changements)->assertOk()->json();
        $this->assertSame([], $r1['rejets']);
        $this->assertSame([$s], $r1['acceptes']['sacrements']);
        $this->assertSame($c, $r1['donnees']['fideles'][0]['ceb_uuid']);
        $this->assertSame($p, $r1['donnees']['sacrements'][0]['paroisse_uuid']);

        // Connexion coupée avant la réponse : l'appareil renvoie tout
        $r2 = $this->synchro($jeton, 0, $changements)->assertOk()->json();
        $this->assertSame([], $r2['rejets']);
        $this->assertSame(1, Paroisse::count());
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
        $p = Paroisse::create(['nom' => 'A']);
        $ceb = Ceb::create(['paroisse_id' => $p->id, 'nom' => 'CEB']);
        $revision = $this->synchro($jeton)->json('revision');

        $ceb->update(['responsable' => 'Mme Yao']);
        $r = $this->synchro($jeton, $revision)->json();
        $this->assertCount(1, $r['donnees']['cebs']);
        $this->assertSame('Mme Yao', $r['donnees']['cebs'][0]['responsable']);
        $this->assertCount(0, $r['donnees']['paroisses']);

        $uuid = $ceb->uuid;
        $ceb->delete();
        $r = $this->synchro($jeton, $r['revision'])->json();
        $this->assertSame([['table_nom' => 'cebs', 'uuid' => $uuid]], json_decode(json_encode($r['suppressions']), true));
    }

    public function test_elements_refuses_avec_la_raison(): void
    {
        $admin = User::factory()->admin()->create();
        $jeton = $this->connecter($admin)['jeton'];
        $p = Paroisse::create(['nom' => 'A']);
        $f = Fidele::create(['paroisse_id' => $p->id, 'nom' => 'X', 'prenoms' => 'Y', 'sexe' => 'M', 'statut' => 'actif']);
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
        $a = Paroisse::create(['nom' => 'A']);
        $b = Paroisse::create(['nom' => 'B']);
        Fidele::create(['paroisse_id' => $a->id, 'nom' => 'ChezA', 'prenoms' => 'x', 'sexe' => 'M', 'statut' => 'actif']);
        Fidele::create(['paroisse_id' => $b->id, 'nom' => 'ChezB', 'prenoms' => 'x', 'sexe' => 'M', 'statut' => 'actif']);

        $secretaire = User::factory()->create(['role' => 'secretaire', 'paroisse_id' => $a->id]);
        $r = $this->synchro($this->connecter($secretaire)['jeton'])->json();
        $this->assertSame(['ChezA'], array_column($r['donnees']['fideles'], 'nom'));
        $this->assertSame(['A'], array_column($r['donnees']['paroisses'], 'nom'));
        $this->assertArrayNotHasKey('operations', $r['donnees']);

        // Un fidèle envoyé pour la paroisse B est enregistré dans la paroisse A
        $nouveau = (string) Str::uuid();
        $r = $this->synchro($this->connecter($secretaire)['jeton'], 0, ['fideles' => [
            ['uuid' => $nouveau, 'paroisse_uuid' => $b->uuid, 'nom' => 'Nouveau', 'prenoms' => 'x', 'sexe' => 'F', 'statut' => 'actif'],
        ]])->json();
        $this->assertSame([], $r['rejets']);
        $this->assertSame($a->id, Fidele::where('uuid', $nouveau)->value('paroisse_id'));

        // Le trésorier ne reçoit pas les fidèles et n'a pas le droit d'en envoyer
        $tresorier = User::factory()->create(['role' => 'tresorier', 'paroisse_id' => $a->id]);
        $r = $this->synchro($this->connecter($tresorier)['jeton'], 0, ['fideles' => [
            ['uuid' => (string) Str::uuid(), 'paroisse_uuid' => $a->uuid, 'nom' => 'Non', 'prenoms' => 'x', 'sexe' => 'F', 'statut' => 'actif'],
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
}
