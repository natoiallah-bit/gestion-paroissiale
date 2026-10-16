<?php

namespace App\Models;

class Paroisse extends Fiche
{
    protected $table = 'paroisses';

    public static function cleRessource(): string
    {
        return 'paroisses';
    }

    public function obstacleSuppression(): ?string
    {
        foreach (['Ceb' => 'CEB', 'Fidele' => 'fidèle', 'Mouvement' => 'mouvement', 'Operation' => 'opération', 'Annonce' => 'annonce', 'Activite' => 'activité'] as $classe => $mot) {
            $c = "App\\Models\\$classe";
            if ($c::where('paroisse_id', $this->id)->exists()) {
                return "Cette paroisse contient encore des données ({$mot}…) : elle ne peut pas être supprimée.";
            }
        }

        return null;
    }
}
