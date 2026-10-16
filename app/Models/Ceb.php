<?php

namespace App\Models;

class Ceb extends Fiche
{
    protected $table = 'cebs';

    public static function cleRessource(): string
    {
        return 'cebs';
    }

    public function detacher(): void
    {
        foreach (Fidele::where('ceb_id', $this->id)->get() as $fidele) {
            $fidele->update(['ceb_id' => null]);
        }
    }
}
