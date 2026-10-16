<?php

namespace App\Models;

use App\Models\Concerns\Synchronisable;
use Illuminate\Database\Eloquent\Model;

class Vicariat extends Model
{
    use Synchronisable;

    protected $table = 'vicariat';

    protected $fillable = ['uuid', 'nom', 'archidiocese', 'doyenne', 'responsable', 'adresse', 'telephone', 'pied_extrait'];

    // Une seule ligne de paramètres pour toute l'application
    public static function courant(): self
    {
        return static::query()->first() ?? static::create(['nom' => 'Christ-Roi de Koundoul Ngama', 'archidiocese' => 'N\'Djamena', 'doyenne' => 'N\'Djamena Sud']);
    }
}
