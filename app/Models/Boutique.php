<?php

namespace App\Models;

use App\Models\Concerns\Synchronisable;
use Illuminate\Database\Eloquent\Model;

class Boutique extends Model
{
    use Synchronisable;

    protected $table = 'boutique';

    protected $fillable = [
        'uuid', 'nom', 'adresse', 'telephone', 'pied_ticket'];

    // Une seule ligne de paramètres pour toute l'application
    public static function courante(): self
    {
        return static::query()->first() ?? static::create(['nom' => 'Ma Boutique']);
    }
}
