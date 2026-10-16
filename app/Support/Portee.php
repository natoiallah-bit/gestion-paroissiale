<?php

namespace App\Support;

use App\Models\Fiche;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Un compte rattaché à une paroisse (secrétaire, trésorier) ne voit que sa paroisse
 * — plus, en lecture, les annonces, activités et mouvements de tout le vicariat.
 * L'administrateur voit tout.
 */
class Portee
{
    public static function restreindre(Builder $requete, ?User $user, string $cle): Builder
    {
        if (! $user || ! $user->estLimiteAUneParoisse()) {
            return $requete;
        }
        $paroisse = $user->paroisse_id;

        return match (Ressources::get($cle)['portee']) {
            'aucune' => $requete->where('id', $paroisse),
            'facultative' => $requete->where(fn ($w) => $w->where('paroisse_id', $paroisse)->orWhereNull('paroisse_id')),
            default => $requete->where('paroisse_id', $paroisse),
        };
    }

    /** Peut-on modifier ou supprimer cette fiche ? (les fiches « tout le vicariat » sont à l'administrateur) */
    public static function peutModifier(?User $user, string $cle, Fiche $fiche): bool
    {
        if (! Ressources::peutEcrire($user, $cle)) {
            return false;
        }
        if (! $user->estLimiteAUneParoisse()) {
            return true;
        }

        return match (Ressources::get($cle)['portee']) {
            'aucune' => false,
            default => $fiche->paroisse_id === $user->paroisse_id,
        };
    }
}
