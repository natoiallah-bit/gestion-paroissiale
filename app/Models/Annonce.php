<?php

namespace App\Models;

class Annonce extends Fiche
{
    protected $table = 'annonces';

    public static function cleRessource(): string
    {
        return 'annonces';
    }
}
