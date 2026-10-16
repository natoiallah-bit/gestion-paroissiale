<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vicariat;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Installation : crée la fiche du vicariat et le compte administrateur.
     * Identifiants modifiables via ADMIN_EMAIL / ADMIN_PASSWORD dans .env.
     */
    public function run(): void
    {
        Vicariat::courant();

        $email = env('ADMIN_EMAIL', 'admin@paroisse.local');
        if (! User::where('email', $email)->exists()) {
            User::create([
                'name' => 'Administrateur',
                'email' => $email,
                'password' => env('ADMIN_PASSWORD', 'changer-ce-mot-de-passe'),
                'role' => 'admin',
                'actif' => true,
            ]);
            $this->command?->warn("Compte administrateur créé : {$email} — changez le mot de passe après la première connexion.");
        }
    }
}
