<?php

namespace App\Support;

use App\Models\Ceb;
use App\Models\Fiche;
use App\Models\Fidele;
use App\Models\Paroisse;
use App\Models\Sacrement;
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

    /** Un compte rattaché à une paroisse travaille toujours dans cette paroisse : on la complète avant de valider. */
    public static function completer(string $cle, array $donnees, User $user): array
    {
        if ($user->estLimiteAUneParoisse() && in_array(Ressources::get($cle)['portee'], ['obligatoire', 'facultative'], true)) {
            $donnees['paroisse_uuid'] = Paroisse::find($user->paroisse_id)?->uuid;
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
            self::refuser('Cette fiche ne fait pas partie de votre paroisse.');
        }

        // Un compte rattaché à une paroisse travaille toujours dans cette paroisse
        if ($user->estLimiteAUneParoisse() && in_array($def['portee'], ['obligatoire', 'facultative'], true)) {
            $donnees['paroisse_uuid'] = Paroisse::find($user->paroisse_id)?->uuid;
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

            if ($cle === 'sacrements') {
                $fiche->paroisse_id = $references['fidele_uuid']?->paroisse_id ?? $fiche->fidele?->paroisse_id;
            }
            if ($cle === 'operations' && ! $fiche->exists) {
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
            $paroisseId = $a['paroisse_id'] ?? $existant?->paroisse_id;
            $cebId = array_key_exists('ceb_id', $a) ? $a['ceb_id'] : $existant?->ceb_id;
            if ($cebId && ! Ceb::where('id', $cebId)->where('paroisse_id', $paroisseId)->exists()) {
                $erreur('ceb_uuid', 'Cette CEB n\'appartient pas à la paroisse du fidèle.');
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
            if ($user->estLimiteAUneParoisse() && $fidele->paroisse_id !== $user->paroisse_id) {
                $erreur('fidele_uuid', 'Ce fidèle ne fait pas partie de votre paroisse.');
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
            $sens = $a['sens'] ?? $existant?->sens;
            $categorie = $a['categorie'] ?? $existant?->categorie;
            $permises = $sens === 'recette' ? Ressources::CATEGORIES_RECETTES : Ressources::CATEGORIES_DEPENSES;
            if (! in_array($categorie, $permises, true)) {
                $erreur('categorie', 'Cette catégorie ne correspond pas à une '.($sens === 'recette' ? 'recette' : 'dépense').'.');
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
            $paroisseId = $a['paroisse_id'] ?? $existant?->paroisse_id;
            if ($mouvement->paroisse_id && $paroisseId && $mouvement->paroisse_id !== $paroisseId) {
                $erreur('mouvement_uuid', 'Ce mouvement appartient à une autre paroisse.');
            }
        }
    }

    private static function refuser(string $message): never
    {
        throw ValidationException::withMessages(['droit' => $message]);
    }
}
