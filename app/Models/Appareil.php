<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Un téléphone ou un ordinateur où l'application est installée
class Appareil extends Model
{
    protected $fillable = ['uuid', 'nom', 'code', 'user_id', 'derniere_synchro'];

    protected function casts(): array
    {
        return ['derniere_synchro' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function enregistrer(string $uuid, string $nom, User $user): self
    {
        $appareil = static::firstOrNew(['uuid' => $uuid]);
        $appareil->nom = $nom;
        $appareil->user_id = $user->id;
        if (! $appareil->exists) {
            // A1, A2… : court, pour rester lisible sur les tickets
            $appareil->code = 'A'.((int) static::max('id') + 1);
        }
        $appareil->save();

        return $appareil;
    }
}
