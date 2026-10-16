<?php

namespace App\Models;

class Secteur extends Fiche
{
    protected $table = 'secteurs';

    public static function cleRessource(): string
    {
        return 'secteurs';
    }

    public function obstacleSuppression(): ?string
    {
        foreach (['Ceb' => 'CEB', 'Fidele' => 'fidèle', 'Mouvement' => 'mouvement', 'Operation' => 'opération', 'Depot' => 'dépôt', 'Annonce' => 'annonce', 'Activite' => 'activité'] as $classe => $mot) {
            $c = "App\\Models\\$classe";
            if ($c::where('secteur_id', $this->id)->exists()) {
                return "Ce secteur contient encore des données ({$mot}…) : il ne peut pas être supprimé.";
            }
        }

        return null;
    }
}
