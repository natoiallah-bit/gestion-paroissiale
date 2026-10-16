<?php

namespace App\Models;

class Fidele extends Fiche
{
    protected $table = 'fideles';

    public static function cleRessource(): string
    {
        return 'fideles';
    }

    public function sacrements()
    {
        return $this->hasMany(Sacrement::class);
    }

    public function obstacleSuppression(): ?string
    {
        if (Inscription::where('fidele_id', $this->id)->exists()) {
            return 'Ce fidèle est inscrit à la catéchèse : mettez-le en « Transféré » ou « Décédé » plutôt que de le supprimer.';
        }

        return $this->sacrements()->exists() ? 'Ce fidèle a des sacrements enregistrés : mettez-le en « Décédé » ou « Transféré » plutôt que de le supprimer.' : null;
    }

    public function nomComplet(): string
    {
        return trim($this->prenoms.' '.mb_strtoupper($this->nom));
    }

    public function intitule(): string
    {
        return $this->nomComplet();
    }

    public function age(): ?int
    {
        return $this->jour('date_naissance')?->age;
    }
}
