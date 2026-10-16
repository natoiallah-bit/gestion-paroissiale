<?php

namespace Database\Seeders;

use App\Models\Activite;
use App\Models\Annonce;
use App\Models\Ceb;
use App\Models\Fidele;
use App\Models\Mouvement;
use App\Models\Operation;
use App\Models\Paroisse;
use App\Models\Sacrement;
use App\Models\User;
use App\Models\Vicariat;
use Illuminate\Database\Seeder;

/** Données d'exemple pour essayer l'application : php artisan db:seed --class=DemoSeeder */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);
        Vicariat::courant()->update(['nom' => 'Vicariat Saint-Jean', 'diocese' => 'Exemple']);

        $noms = ['Koné', 'Traoré', 'Yao', 'Kouassi', 'Diallo', 'Bamba', 'N\'Guessan', 'Coulibaly'];
        $prenoms = ['Awa', 'Moussa', 'Marie', 'Jean', 'Fanta', 'Paul', 'Esther', 'Joseph', 'Rose', 'Pierre'];
        $admin = User::where('role', 'admin')->first();

        foreach (['Saint-Paul' => 'Abidjan', 'Sainte-Marie' => 'Bouaké'] as $nom => $localite) {
            $p = Paroisse::firstOrCreate(['nom' => $nom], ['localite' => $localite, 'cure' => 'Abbé '.$noms[array_rand($noms)]]);
            $slug = strtolower(str_replace(['-', ' '], '', $nom));

            foreach (['secretaire' => 'secretaire', 'tresorier' => 'tresorier'] as $role => $prefixe) {
                User::firstOrCreate(['email' => "$prefixe.$slug@paroisse.local"], [
                    'name' => ucfirst($role).' '.$nom, 'password' => 'demo1234', 'role' => $role, 'actif' => true, 'paroisse_id' => $p->id,
                ]);
            }

            $cebs = collect(['Saint-Joseph', 'Sainte-Anne', 'Saint-Pierre'])->map(fn ($n) => Ceb::firstOrCreate(
                ['paroisse_id' => $p->id, 'nom' => "CEB $n"], ['quartier' => 'Quartier '.$n, 'responsable' => $prenoms[array_rand($prenoms)].' '.$noms[array_rand($noms)]]
            ));

            foreach (['Légion de Marie' => 'mouvement', 'Chorale' => 'service', 'Servants de messe' => 'service'] as $n => $type) {
                Mouvement::firstOrCreate(['paroisse_id' => $p->id, 'nom' => $n], ['type' => $type, 'nombre_membres' => rand(15, 60), 'responsable' => $prenoms[array_rand($prenoms)]]);
            }

            if (Fidele::where('paroisse_id', $p->id)->exists()) {
                continue;
            }
            for ($i = 0; $i < 30; $i++) {
                $f = Fidele::create([
                    'paroisse_id' => $p->id, 'ceb_id' => $cebs->random()->id,
                    'nom' => $nom_f = $noms[array_rand($noms)], 'prenoms' => $prenoms[array_rand($prenoms)],
                    'sexe' => rand(0, 1) ? 'M' : 'F', 'date_naissance' => now()->subYears(rand(3, 80))->toDateString(),
                    'famille' => "Famille $nom_f", 'statut' => 'actif',
                ]);
                Sacrement::create([
                    'fidele_id' => $f->id, 'paroisse_id' => $p->id, 'type' => 'bapteme',
                    'date_sacrement' => max($f->date_naissance, now()->subYears(rand(0, 2))->toDateString()), 'lieu' => $nom, 'numero_registre' => 'B-'.($i + 1),
                ]);
            }
            for ($m = 0; $m < 3; $m++) {
                Operation::create(['paroisse_id' => $p->id, 'user_id' => $admin->id, 'sens' => 'recette', 'categorie' => 'Quête', 'libelle' => 'Quête du dimanche', 'montant' => rand(15, 60) * 1000, 'date_operation' => now()->subWeeks($m)->toDateString()]);
            }
            Operation::create(['paroisse_id' => $p->id, 'user_id' => $admin->id, 'sens' => 'depense', 'categorie' => 'Électricité / eau', 'libelle' => 'Facture du mois', 'montant' => 18000, 'date_operation' => now()->subDays(3)->toDateString()]);
            Annonce::create(['paroisse_id' => $p->id, 'titre' => 'Messe de rentrée', 'contenu' => 'Dimanche à 9 h, présence de tous les mouvements.', 'date_debut' => today()->toDateString()]);
            Activite::create(['paroisse_id' => $p->id, 'titre' => 'Réunion des responsables de CEB', 'date_activite' => today()->addDays(6)->toDateString(), 'heure' => '17:00', 'lieu' => 'Salle paroissiale']);
        }

        Annonce::firstOrCreate(['titre' => 'Rencontre vicariale'], ['contenu' => 'Rencontre des curés et des délégués de chaque paroisse.', 'date_debut' => today()->toDateString()]);
        Activite::firstOrCreate(['titre' => 'Pèlerinage vicarial'], ['date_activite' => today()->addDays(30)->toDateString(), 'lieu' => 'Sanctuaire marial']);
    }
}
