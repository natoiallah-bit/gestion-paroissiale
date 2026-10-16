<?php

namespace App\Support;

use App\Models\Fiche;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Un compte rattaché à un secteur (secrétaire, trésorier) ne voit que son secteur
 * — plus, en lecture, les annonces, activités et mouvements de tout le vicariat.
 * L'administrateur voit tout.
 */
class Portee
{
    public static function restreindre(Builder $requete, ?User $user, string $cle): Builder
    {
        if (! $user || ! $user->estLimiteAUnSecteur()) {
            return $requete;
        }
        $secteur = $user->secteur_id;

        return match (Ressources::get($cle)['portee']) {
            'aucune' => $requete->where('id', $secteur),
            'facultative' => $requete->where(fn ($w) => $w->where('secteur_id', $secteur)->orWhereNull('secteur_id')),
            // Comptes : un secteur ne voit jamais la caisse centrale ni les autres secteurs
            'centrale' => $requete->where('secteur_id', $secteur),
            default => $requete->where('secteur_id', $secteur),
        };
    }

    /** Peut-on modifier ou supprimer cette fiche ? (les fiches « tout le vicariat » sont à l'administrateur) */
    public static function peutModifier(?User $user, string $cle, Fiche $fiche): bool
    {
        if (! Ressources::peutEcrire($user, $cle)) {
            return false;
        }
        if (! $user->estLimiteAUnSecteur()) {
            return true;
        }

        return match (Ressources::get($cle)['portee']) {
            'aucune' => false,
            default => $fiche->secteur_id === $user->secteur_id,
        };
    }
}
