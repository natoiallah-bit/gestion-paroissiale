<?php

namespace App\Models;

use App\Models\Concerns\Synchronisable;
use App\Support\Ressources;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Base des fiches décrites dans App\Support\Ressources : champs, conversions et
 * relations viennent de cette description (une seule source de vérité).
 */
abstract class Fiche extends Model
{
    use Synchronisable;

    abstract public static function cleRessource(): string;

    public function getFillable(): array
    {
        $colonnes = array_map(Ressources::colonne(...), Ressources::get(static::cleRessource())['champs']);

        return array_merge(['uuid'], $colonnes, $this->colonnesSupplementaires());
    }

    protected function colonnesSupplementaires(): array
    {
        // Les fiches « dérivées » (sacrement, inscription, séance) gardent le secteur de leur fiche mère
        return Ressources::get(static::cleRessource())['portee'] === 'derivee' ? ['secteur_id'] : [];
    }

    protected function casts(): array
    {
        $casts = [];
        foreach (Ressources::get(static::cleRessource())['champs'] as $champ) {
            $casts[$champ['nom']] = match ($champ['type']) {
                'entier', 'montant' => 'integer',
                default => null,
            };
        }

        return array_filter($casts);
    }

    /** Les dates sont gardées en texte AAAA-MM-JJ (comparaisons et totaux fiables) ; jour() les convertit pour l'affichage. */
    public function jour(string $champ): ?Carbon
    {
        return $this->{$champ} ? Carbon::parse($this->{$champ}) : null;
    }

    /** Le fidèle, la CEB… doivent d'abord être détachés de ce qui s'y rapporte. À surcharger. */
    public function obstacleSuppression(): ?string
    {
        return null;
    }

    /** Appelé avant la suppression : détache proprement ce qui pointe vers cette fiche. */
    public function detacher(): void {}

    public function intitule(): string
    {
        return (string) ($this->nom ?? $this->titre ?? $this->libelle ?? '#'.$this->id);
    }
}
