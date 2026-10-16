<?php

namespace App\Models;

class Mouvement extends Fiche
{
    protected $table = 'mouvements';

    public static function cleRessource(): string
    {
        return 'mouvements';
    }

    public function detacher(): void
    {
        foreach (Activite::where('mouvement_id', $this->id)->get() as $activite) {
            $activite->update(['mouvement_id' => null]);
        }
    }
}
