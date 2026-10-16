<?php

use App\Support\AdresseReseau;
use App\Support\Ressources;
use Illuminate\Support\Facades\Artisan;

// Affiche l'adresse à taper dans les applications (téléphone, ordinateur)
Artisan::command('vicariat:adresses {--port=8200}', function () {
    $port = (int) $this->option('port');
    $urls = AdresseReseau::urls($port);

    $this->newLine();
    $this->line('  Sur CET ordinateur (navigateur ou application Windows) :');
    $this->info("      http://127.0.0.1:{$port}");
    $this->newLine();
    if ($urls) {
        $this->line('  Sur les TÉLÉPHONES connectés au même Wi-Fi, adresse du serveur :');
        foreach ($urls as $url) {
            $this->info("      {$url}");
        }
    } else {
        $this->warn('  Aucun réseau détecté : connectez cet ordinateur au Wi-Fi de la boutique.');
    }
    $this->newLine();
})->purpose("Affiche l'adresse du serveur pour les applications");

// Exporte la description des fiches pour les applications (application/src/ressources.json).
// À relancer après toute modification de App\Support\Ressources ; un test vérifie qu'elle est à jour.
Artisan::command('vicariat:ressources {--verifier}', function () {
    $json = Ressources::pourApplication();
    $fichier = base_path('application/src/ressources.json');

    if ($this->option('verifier')) {
        return is_file($fichier) && file_get_contents($fichier) === $json ? self::SUCCESS : self::FAILURE;
    }
    file_put_contents($fichier, $json);
    $this->info('application/src/ressources.json mis à jour.');
})->purpose('Exporte la description des fiches pour les applications');
