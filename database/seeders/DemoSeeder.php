<?php

namespace Database\Seeders;

use App\Models\Categorie;
use App\Models\Client;
use App\Models\Fournisseur;
use App\Models\Produit;
use App\Models\User;
use App\Models\Vente;
use Illuminate\Database\Seeder;

/**
 * Données d'exemple pour essayer l'application : php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);
        $gerant = User::where('role', 'gerant')->firstOrFail();

        User::firstOrCreate(['email' => 'vendeur@boutique.local'], [
            'name' => 'Vendeur', 'password' => 'vendeur1234', 'role' => 'vendeur', 'actif' => true,
        ]);

        $fournisseur = Fournisseur::firstOrCreate(['nom' => 'Grossiste du marché'], ['telephone' => '07 00 00 00 00']);

        $catalogue = [
            'Boissons' => [['Eau minérale 1,5 L', 250, 400], ['Coca-Cola 33 cl', 300, 500], ['Jus de bissap 50 cl', 200, 350]],
            'Alimentation' => [['Riz parfumé 5 kg', 3500, 4250], ['Huile 1 L', 1100, 1400], ['Sucre en morceaux', 600, 800], ['Lait en poudre 400 g', 1800, 2300]],
            'Hygiène' => [['Savon de Marseille', 250, 400], ['Dentifrice', 500, 750], ['Papier toilette x4', 800, 1100]],
        ];

        foreach ($catalogue as $nomCategorie => $produits) {
            $categorie = Categorie::firstOrCreate(['nom' => $nomCategorie]);
            foreach ($produits as [$nom, $achat, $vente]) {
                $produit = Produit::firstOrCreate(['nom' => $nom], [
                    'categorie_id' => $categorie->id, 'prix_achat' => $achat, 'prix_vente' => $vente, 'seuil_alerte' => 5,
                ]);
                if ($produit->wasRecentlyCreated) {
                    $produit->mouvementer(rand(8, 40), 'entree', $gerant->id, [
                        'prix_achat_unitaire' => $achat, 'fournisseur_id' => $fournisseur->id, 'note' => 'Stock de démonstration',
                    ]);
                }
            }
        }

        $awa = Client::firstOrCreate(['nom' => 'Awa Koné'], ['telephone' => '05 11 22 33 44', 'adresse' => 'Quartier Commerce']);
        Client::firstOrCreate(['nom' => 'Moussa Traoré'], ['telephone' => '01 55 66 77 88']);

        if (Vente::count() === 0) {
            $eau = Produit::where('nom', 'Eau minérale 1,5 L')->first();
            $riz = Produit::where('nom', 'Riz parfumé 5 kg')->first();
            Vente::enregistrer([['produit_id' => $eau->id, 'quantite' => 3]], 1200, 'especes', null, $gerant->id);
            Vente::enregistrer([['produit_id' => $riz->id, 'quantite' => 1], ['produit_id' => $eau->id, 'quantite' => 2]], 3000, 'especes', $awa->id, $gerant->id);
        }
    }
}
