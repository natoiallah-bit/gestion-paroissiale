<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Donne à un modèle un uuid stable et une révision qui augmente à chaque écriture,
 * pour que les appareils puissent récupérer les changements, et garde la trace
 * des suppressions.
 */
trait Synchronisable
{
    public static function bootSynchronisable(): void
    {
        static::saving(function ($modele) {
            $modele->uuid ??= (string) Str::uuid();
            $modele->revision = static::prochaineRevision();
        });

        static::deleted(function ($modele) {
            DB::table('suppressions')->insert([
                'table_nom' => $modele->getTable(),
                'uuid' => $modele->uuid,
                'revision' => static::prochaineRevision(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public static function prochaineRevision(): int
    {
        // L'incrément verrouille la ligne jusqu'à la fin de la transaction en cours :
        // les révisions sont donc validées dans l'ordre.
        DB::table('sync_compteur')->where('id', 1)->increment('valeur');

        return (int) DB::table('sync_compteur')->where('id', 1)->value('valeur');
    }

    public static function parUuid(?string $uuid): ?static
    {
        return $uuid ? static::where('uuid', $uuid)->first() : null;
    }
}
