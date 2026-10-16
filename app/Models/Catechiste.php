<?php

namespace App\Models;

class Catechiste extends Fiche
{
    protected $table = 'catechistes';

    public static function cleRessource(): string
    {
        return 'catechistes';
    }

    public function detacher(): void
    {
        foreach (GroupeCatechese::where('catechiste_id', $this->id)->get() as $g) {
            $g->update(['catechiste_id' => null]);
        }
        foreach (Seance::where('catechiste_id', $this->id)->get() as $s) {
            $s->update(['catechiste_id' => null]);
        }
    }

    public function intitule(): string
    {
        return trim($this->prenoms.' '.mb_strtoupper($this->nom));
    }
}
