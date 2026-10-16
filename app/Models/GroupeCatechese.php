<?php

namespace App\Models;

class GroupeCatechese extends Fiche
{
    protected $table = 'groupes_catechese';

    public static function cleRessource(): string
    {
        return 'groupes';
    }

    public function obstacleSuppression(): ?string
    {
        return Inscription::where('groupe_id', $this->id)->exists() || Seance::where('groupe_id', $this->id)->exists()
            ? 'Ce groupe a des inscrits ou des séances : il ne peut pas être supprimé.'
            : null;
    }

    public function intitule(): string
    {
        return $this->nom.' ('.$this->annee.')';
    }
}
