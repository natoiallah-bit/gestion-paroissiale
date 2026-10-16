<?php

use App\Support\AdresseReseau;
use Illuminate\Support\Facades\Artisan;

// Affiche l'adresse à taper dans les applications (téléphone, ordinateur)
Artisan::command('paroisse:adresses {--port=8200}', function () {
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
