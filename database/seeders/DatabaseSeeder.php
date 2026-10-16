<?php

namespace Database\Seeders;

use App\Models\Boutique;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Installation : crée le compte gérant et la fiche boutique.
     * Identifiants modifiables via GERANT_EMAIL / GERANT_PASSWORD dans .env.
     */
    public function run(): void
    {
        Boutique::courante();

        $email = env('GERANT_EMAIL', 'gerant@boutique.local');
        if (! User::where('email', $email)->exists()) {
            User::create([
                'name' => 'Gérant',
                'email' => $email,
                'password' => env('GERANT_PASSWORD', 'changer-ce-mot-de-passe'),
                'role' => 'gerant',
                'actif' => true,
            ]);
            $this->command?->warn("Compte gérant créé : {$email} — changez le mot de passe après la première connexion.");
        }
    }
}
