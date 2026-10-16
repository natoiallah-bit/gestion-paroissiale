<?php

namespace App\Models;

use App\Support\Ressources;

class Sacrement extends Fiche
{
    protected $table = 'sacrements';

    public static function cleRessource(): string
    {
        return 'sacrements';
    }

    public function intitule(): string
    {
        return (Ressources::SACREMENTS[$this->type] ?? $this->type).' — '.($this->fidele?->nomComplet() ?? '?');
    }
}
