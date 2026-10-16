<?php

namespace App\Models;

class Inscription extends Fiche
{
    protected $table = 'inscriptions';

    public static function cleRessource(): string
    {
        return 'inscriptions';
    }

    public function intitule(): string
    {
        return ($this->fidele?->nomComplet() ?? '?').' — '.($this->groupe?->nom ?? '?');
    }
}
