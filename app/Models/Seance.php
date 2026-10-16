<?php

namespace App\Models;

class Seance extends Fiche
{
    protected $table = 'seances';

    public static function cleRessource(): string
    {
        return 'seances';
    }

    public function intitule(): string
    {
        return ($this->groupe?->nom ?? '?').' — '.$this->theme;
    }
}
