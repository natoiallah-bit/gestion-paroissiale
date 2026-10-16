<?php

namespace App\Models;

class Depot extends Fiche
{
    protected $table = 'depots';

    public static function cleRessource(): string
    {
        return 'depots';
    }
}
