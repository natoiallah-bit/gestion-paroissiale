<?php

namespace App\Models;

class Operation extends Fiche
{
    protected $table = 'operations';

    public static function cleRessource(): string
    {
        return 'operations';
    }

    public function estRecette(): bool
    {
        return $this->sens === 'recette';
    }
}
