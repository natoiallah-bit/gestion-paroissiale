<?php

namespace App\Models;

class Activite extends Fiche
{
    protected $table = 'activites';

    public static function cleRessource(): string
    {
        return 'activites';
    }
}
