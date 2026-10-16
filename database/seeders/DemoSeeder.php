<?php

namespace Database\Seeders;

use App\Models\Activite;
use App\Models\Annonce;
use App\Models\Catechiste;
use App\Models\Ceb;
use App\Models\Depot;
use App\Models\Fidele;
use App\Models\GroupeCatechese;
use App\Models\Inscription;
use App\Models\Mouvement;
use App\Models\Operation;
use App\Models\Sacrement;
use App\Models\Seance;
use App\Models\Secteur;
use App\Models\User;
use App\Support\Ressources;
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

        foreach (['Koundoul Nord', 'Koundoul Sud', 'Ngama'] as $n => $nom) {
            $s = Secteur::firstOrCreate(['nom' => $nom], ['responsable' => $prenoms[array_rand($prenoms)].' '.$noms[array_rand($noms)]]);
            $slug = 'secteur'.($n + 1);

            foreach (['secretaire', 'tresorier', 'catechese'] as $role) {
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

            // Catéchèse : catéchistes, groupes de l'année pastorale en cours, inscrits et séances
            $annee = Ressources::anneePastorale();
            $catechistes = collect([['Marie', 'Yao'], ['Joseph', 'Mahamat'], ['Lydie', 'Betoloum']])->map(fn ($c) => Catechiste::create([
                'secteur_id' => $s->id, 'prenoms' => $c[0], 'nom' => $c[1], 'formation' => 'initiale', 'statut' => 'actif', 'date_debut' => now()->subYears(rand(1, 6))->toDateString(),
            ]));
            $fideles = Fidele::where('secteur_id', $s->id)->get();
            foreach (['eveil' => 'Éveil à la foi', 'communion' => 'Première communion', 'confirmation' => 'Confirmation'] as $niveau => $libelle) {
                $groupe = GroupeCatechese::create([
                    'secteur_id' => $s->id, 'catechiste_id' => $catechistes->random()->id, 'annee' => $annee, 'nom' => "$libelle — $nom",
                    'niveau' => $niveau, 'jour_rencontre' => 'samedi', 'heure' => '15:00', 'lieu' => 'Chapelle de '.$nom,
                ]);
                foreach ($fideles->splice(0, 6) as $f) {
                    Inscription::create(['fidele_id' => $f->id, 'groupe_id' => $groupe->id, 'secteur_id' => $s->id, 'date_inscription' => now()->subWeeks(3)->toDateString(), 'statut' => 'inscrit']);
                }
                foreach ([3, 2, 1] as $semaines) {
                    Seance::create(['groupe_id' => $groupe->id, 'secteur_id' => $s->id, 'catechiste_id' => $groupe->catechiste_id, 'date_seance' => now()->subWeeks($semaines)->toDateString(),
                        'theme' => 'Séance '.(4 - $semaines), 'presents' => rand(4, 6), 'absents' => rand(0, 2)]);
                }
            }

            Annonce::create(['secteur_id' => $s->id, 'titre' => 'Réunion des responsables de CEB', 'contenu' => 'Dimanche après la messe.', 'date_debut' => today()->toDateString()]);
            Activite::create(['secteur_id' => $s->id, 'titre' => 'Rencontre du mouvement des jeunes', 'date_activite' => today()->addDays(6 + $n)->toDateString(), 'heure' => '16:00', 'lieu' => $nom]);
        }

        Operation::firstOrCreate(['libelle' => 'Entretien de l\'église', 'secteur_id' => null], ['user_id' => $admin->id, 'sens' => 'depense', 'categorie' => 'Entretien / travaux', 'montant' => 30000, 'date_operation' => now()->subDays(2)->toDateString()]);
        Annonce::firstOrCreate(['titre' => 'Fête patronale — Christ-Roi'], ['contenu' => 'Messe solennelle, tous les secteurs sont invités.', 'date_debut' => today()->toDateString()]);
        Activite::firstOrCreate(['titre' => 'Assemblée des conseils économiques'], ['date_activite' => today()->addDays(20)->toDateString(), 'lieu' => 'Vicariat Christ-Roi']);
    }
}
