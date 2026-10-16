<?php

namespace Database\Seeders;

use App\Models\Activite;
use App\Models\Annonce;
use App\Models\Ceb;
use App\Models\Depot;
use App\Models\Fidele;
use App\Models\Mouvement;
use App\Models\Operation;
use App\Models\Sacrement;
use App\Models\Secteur;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Données d'exemple (noms inventés) pour essayer l'application :
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        $noms = ['Ndolngar', 'Mahamat', 'Djimadoum', 'Nodjigoto', 'Ngarbaye', 'Allarassem', 'Betoloum', 'Tchoroma'];
        $prenoms = ['Marie', 'Joseph', 'Esther', 'Jean', 'Rose', 'Pierre', 'Anne', 'Paul', 'Lydie', 'Michel'];
        $admin = User::where('role', 'admin')->first();

        User::firstOrCreate(['email' => 'tresorier.vicariat@vicariat.local'], [
            'name' => 'Trésorier du vicariat', 'password' => 'demo1234', 'role' => 'tresorier', 'actif' => true,
        ]);

        foreach (['Secteur 1', 'Secteur 2', 'Secteur 3'] as $n => $nom) {
            $s = Secteur::firstOrCreate(['nom' => $nom], ['responsable' => $prenoms[array_rand($prenoms)].' '.$noms[array_rand($noms)]]);
            $slug = 'secteur'.($n + 1);

            foreach (['secretaire', 'tresorier'] as $role) {
                User::firstOrCreate(['email' => "$role.$slug@vicariat.local"], [
                    'name' => ucfirst($role).' '.$nom, 'password' => 'demo1234', 'role' => $role, 'actif' => true, 'secteur_id' => $s->id,
                ]);
            }

            $cebs = collect(['Saint-Joseph', 'Sainte-Anne', 'Saint-Pierre', 'Sainte-Thérèse'])->map(fn ($c) => Ceb::firstOrCreate(
                ['secteur_id' => $s->id, 'nom' => "CEB $c"], ['quartier' => 'Quartier '.$c, 'responsable' => $prenoms[array_rand($prenoms)].' '.$noms[array_rand($noms)]]
            ));

            foreach (['Mouvement des jeunes' => 'jeunes', 'Mouvement des adultes' => 'adultes', 'Pastorale liturgique' => 'pastorale', 'Pastorale familiale' => 'pastorale', 'Chorale' => 'service'] as $m => $type) {
                Mouvement::firstOrCreate(['secteur_id' => $s->id, 'nom' => $m], ['type' => $type, 'nombre_membres' => rand(15, 80), 'responsable' => $prenoms[array_rand($prenoms)]]);
            }

            if (Fidele::where('secteur_id', $s->id)->exists()) {
                continue;
            }
            for ($i = 0; $i < 30; $i++) {
                $nomF = $noms[array_rand($noms)];
                $naissance = now()->subYears(rand(3, 80))->toDateString();
                $f = Fidele::create([
                    'secteur_id' => $s->id, 'ceb_id' => $cebs->random()->id, 'nom' => $nomF, 'prenoms' => $prenoms[array_rand($prenoms)],
                    'sexe' => rand(0, 1) ? 'M' : 'F', 'date_naissance' => $naissance, 'famille' => "Famille $nomF", 'statut' => 'actif',
                ]);
                Sacrement::create([
                    'fidele_id' => $f->id, 'secteur_id' => $s->id, 'type' => 'bapteme', 'numero_registre' => 'B-'.($n + 1).'-'.($i + 1),
                    'date_sacrement' => max($naissance, now()->subYears(rand(0, 2))->toDateString()), 'lieu' => 'Vicariat Christ-Roi',
                ]);
            }

            // Compte de chaque CEB au conseil économique du secteur
            foreach ($cebs as $ceb) {
                foreach ([5, 3, 1] as $semaines) {
                    Operation::create(['secteur_id' => $s->id, 'ceb_id' => $ceb->id, 'user_id' => $admin->id, 'sens' => 'recette', 'categorie' => 'Cotisation de CEB',
                        'libelle' => 'Cotisation mensuelle', 'montant' => rand(5, 25) * 1000, 'date_operation' => now()->subWeeks($semaines)->toDateString()]);
                }
            }
            Operation::create(['secteur_id' => $s->id, 'user_id' => $admin->id, 'sens' => 'depense', 'categorie' => 'Transport', 'libelle' => 'Déplacement réunion', 'montant' => 7500, 'date_operation' => now()->subDays(4)->toDateString()]);

            // Dépôt du secteur à la caisse centrale du vicariat
            Depot::create(['secteur_id' => $s->id, 'user_id' => $admin->id, 'montant' => rand(20, 60) * 1000, 'date_depot' => now()->subDays(10 + $n)->toDateString(), 'reference' => 'R-'.($n + 1).'01', 'deposant' => 'Trésorier '.$nom]);

            Annonce::create(['secteur_id' => $s->id, 'titre' => 'Réunion des responsables de CEB', 'contenu' => 'Dimanche après la messe.', 'date_debut' => today()->toDateString()]);
            Activite::create(['secteur_id' => $s->id, 'titre' => 'Rencontre du mouvement des jeunes', 'date_activite' => today()->addDays(6 + $n)->toDateString(), 'heure' => '16:00', 'lieu' => $nom]);
        }

        Operation::firstOrCreate(['libelle' => 'Entretien de l\'église', 'secteur_id' => null], ['user_id' => $admin->id, 'sens' => 'depense', 'categorie' => 'Entretien / travaux', 'montant' => 30000, 'date_operation' => now()->subDays(2)->toDateString()]);
        Annonce::firstOrCreate(['titre' => 'Fête patronale — Christ-Roi'], ['contenu' => 'Messe solennelle, tous les secteurs sont invités.', 'date_debut' => today()->toDateString()]);
        Activite::firstOrCreate(['titre' => 'Assemblée des conseils économiques'], ['date_activite' => today()->addDays(20)->toDateString(), 'lieu' => 'Vicariat Christ-Roi']);
    }
}
