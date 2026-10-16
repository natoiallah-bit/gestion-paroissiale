<?php

namespace App\Support;

use App\Models\Ceb;
use App\Models\Fiche;
use App\Models\Fidele;
use App\Models\Sacrement;
use App\Models\Secteur;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Seul endroit où une fiche est créée ou modifiée à partir de données validées,
 * que ce soit depuis le site ou depuis une application : mêmes règles partout.
 */
class Enregistreur
{
    // Sacrements qu'on ne reçoit qu'une fois
    private const UNIQUES = ['bapteme', 'eucharistie', 'confirmation', 'obseques'];

    /** Un compte rattaché à un secteur travaille toujours dans ce secteur : on le complète avant de valider. */
    public static function completer(string $cle, array $donnees, User $user): array
    {
        if ($user->estLimiteAUnSecteur() && in_array(Ressources::get($cle)['portee'], ['obligatoire', 'facultative', 'centrale'], true)) {
            $donnees['secteur_uuid'] = Secteur::find($user->secteur_id)?->uuid;
        }

        return $donnees;
    }

    public static function enregistrer(string $cle, array $donnees, User $user, ?Fiche $existant = null): Fiche
    {
        $def = Ressources::get($cle);

        if (! Ressources::peutEcrire($user, $cle)) {
            self::refuser('Votre compte n\'a pas le droit de modifier cette fiche.');
        }
        if ($existant && ! Portee::peutModifier($user, $cle, $existant)) {
            self::refuser('Cette fiche ne fait pas partie de votre secteur.');
        }

        // Un compte rattaché à un secteur travaille toujours dans ce secteur
        if ($user->estLimiteAUnSecteur() && in_array($def['portee'], ['obligatoire', 'facultative', 'centrale'], true)) {
            $donnees['secteur_uuid'] = Secteur::find($user->secteur_id)?->uuid;
        }

        // Valeurs des champs ; les références (uuid) deviennent des identifiants
        $attributs = [];
        $references = [];
        foreach ($def['champs'] as $champ) {
            $valeur = $donnees[$champ['nom']] ?? ($existant ? null : ($champ['defaut'] ?? null));
            if (array_key_exists($champ['nom'], $donnees) === false && $existant) {
                continue;
            }
            if ($valeur === '') {
                $valeur = null;
            }
            if ($champ['type'] === 'date' && $valeur) {
                $valeur = Carbon::parse($valeur)->toDateString();
            }
            if ($champ['type'] === 'ref') {
                $cible = $valeur ? Ressources::get($champ['ref'])['modele']::parUuid($valeur) : null;
                if ($valeur && ! $cible) {
                    self::refuser("{$champ['label']} : fiche introuvable.");
                }
                $references[$champ['nom']] = $cible;
                $attributs[Ressources::colonne($champ)] = $cible?->id;
            } else {
                $attributs[$champ['nom']] = $valeur;
            }
        }

        self::verifier($cle, $attributs, $references, $user, $existant);

        return DB::transaction(function () use ($cle, $def, $donnees, $attributs, $references, $user, $existant) {
            $fiche = $existant ?? new $def['modele'](['uuid' => $donnees['uuid'] ?? null]);
            $fiche->fill($attributs);

            if ($cle === 'operations' && ($references['ceb_uuid'] ?? null)) {
                $fiche->secteur_id = $references['ceb_uuid']->secteur_id;
            }
            if ($cle === 'sacrements') {
                $fiche->secteur_id = $references['fidele_uuid']?->secteur_id ?? $fiche->fidele?->secteur_id;
            }
            if (in_array($cle, ['operations', 'depots'], true) && ! $fiche->exists) {
                $fiche->user_id = $user->id;
            }
            $fiche->save();

            // Des obsèques ferment la fiche du fidèle
            if ($cle === 'sacrements' && $fiche->type === 'obseques') {
                $fidele = Fidele::find($fiche->fidele_id);
                if ($fidele && $fidele->statut !== 'decede') {
                    $fidele->update(['statut' => 'decede']);
                }
            }

            return $fiche;
        });
    }

    private static function verifier(string $cle, array $a, array $refs, User $user, ?Fiche $existant): void
    {
        $erreur = fn (string $champ, string $message) => throw ValidationException::withMessages([$champ => $message]);

        if ($cle === 'fideles') {
            $secteurId = $a['secteur_id'] ?? $existant?->secteur_id;
            $cebId = array_key_exists('ceb_id', $a) ? $a['ceb_id'] : $existant?->ceb_id;
            if ($cebId && ! Ceb::where('id', $cebId)->where('secteur_id', $secteurId)->exists()) {
                $erreur('ceb_uuid', 'Cette CEB n\'appartient pas au secteur du fidèle.');
            }
            if (! empty($a['date_naissance']) && $a['date_naissance'] > now()->toDateString()) {
                $erreur('date_naissance', 'La date de naissance est dans le futur.');
            }
        }

        if ($cle === 'sacrements') {
            $fidele = $refs['fidele_uuid'] ?? $existant?->fidele;
            if (! $fidele) {
                $erreur('fidele_uuid', 'Le fidèle est obligatoire.');
            }
            if ($user->estLimiteAUnSecteur() && $fidele->secteur_id !== $user->secteur_id) {
                $erreur('fidele_uuid', 'Ce fidèle ne fait pas partie de votre secteur.');
            }
            $type = $a['type'] ?? $existant?->type;
            $date = $a['date_sacrement'] ?? $existant?->date_sacrement;
            if ($date && $date > now()->addDay()->toDateString()) {
                $erreur('date_sacrement', 'La date du sacrement est dans le futur.');
            }
            if ($date && $fidele->date_naissance && $date < substr($fidele->date_naissance, 0, 10)) {
                $erreur('date_sacrement', 'Le sacrement est antérieur à la naissance du fidèle.');
            }
            if (in_array($type, self::UNIQUES, true)
                && Sacrement::where('fidele_id', $fidele->id)->where('type', $type)
                    ->when($existant, fn ($q) => $q->where('id', '!=', $existant->id))->exists()) {
                $erreur('type', Ressources::SACREMENTS[$type].' déjà enregistré pour '.$fidele->nomComplet().'.');
            }
        }

        if ($cle === 'operations') {
            $cebId = array_key_exists('ceb_id', $a) ? $a['ceb_id'] : $existant?->ceb_id;
            $secteurId = array_key_exists('secteur_id', $a) ? $a['secteur_id'] : $existant?->secteur_id;
            if ($cebId) {
                $ceb = Ceb::find($cebId);
                if ($secteurId && $ceb->secteur_id !== $secteurId) {
                    $erreur('ceb_uuid', 'Cette CEB n\'appartient pas à ce secteur.');
                }
                if ($user->estLimiteAUnSecteur() && $ceb->secteur_id !== $user->secteur_id) {
                    $erreur('ceb_uuid', 'Cette CEB ne fait pas partie de votre secteur.');
                }
            }
            $sens = $a['sens'] ?? $existant?->sens;
            $categorie = $a['categorie'] ?? $existant?->categorie;
            $permises = $sens === 'recette' ? Ressources::CATEGORIES_RECETTES : Ressources::CATEGORIES_DEPENSES;
            if (! in_array($categorie, $permises, true)) {
                $erreur('categorie', 'Cette catégorie ne correspond pas à une '.($sens === 'recette' ? 'recette' : 'dépense').'.');
            }
        }

        if ($cle === 'depots') {
            $date = $a['date_depot'] ?? $existant?->date_depot;
            if ($date && $date > now()->addDay()->toDateString()) {
                $erreur('date_depot', 'La date du dépôt est dans le futur.');
            }
        }

        if ($cle === 'annonces') {
            $debut = $a['date_debut'] ?? $existant?->date_debut;
            $fin = array_key_exists('date_fin', $a) ? $a['date_fin'] : $existant?->date_fin;
            if ($fin && $debut && $fin < $debut) {
                $erreur('date_fin', 'La date de fin précède la date de début.');
            }
        }

        if ($cle === 'activites' && ! empty($refs['mouvement_uuid'])) {
            $mouvement = $refs['mouvement_uuid'];
            $secteurId = $a['secteur_id'] ?? $existant?->secteur_id;
            if ($mouvement->secteur_id && $secteurId && $mouvement->secteur_id !== $secteurId) {
                $erreur('mouvement_uuid', 'Ce mouvement appartient à un autre secteur.');
            }
        }
    }

    private static function refuser(string $message): never
    {
        throw ValidationException::withMessages(['droit' => $message]);
    }
}
