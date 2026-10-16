<?php

namespace App\Support;

use App\Models\Ceb;
use App\Models\Fiche;
use App\Models\Fidele;
use App\Models\Inscription;
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
            // Sacrement, inscription, séance : le secteur est celui du fidèle ou du groupe
            if ($def['portee'] === 'derivee') {
                $mere = $references[$def['derive_de']] ?? $fiche->{Ressources::relation(Ressources::champ($cle, $def['derive_de']))};
                $fiche->secteur_id = $mere->secteur_id;
            }
            if (in_array($cle, ['operations', 'depots'], true) && ! $fiche->exists) {
                $fiche->user_id = $user->id;
            }
            $fiche->save();

            // Un sacrement reçu fait passer à « admis » l'inscription au niveau qui le prépare
            if ($cle === 'sacrements') {
                self::admettre($fiche);
            }

            if ($cle === 'inscriptions') {
                self::admettreInscription($fiche->load('groupe'));
            }

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

    private static function admettre(Fiche $sacrement): void
    {
        foreach (Inscription::where('fidele_id', $sacrement->fidele_id)->where('statut', 'inscrit')->with('groupe')->get() as $inscription) {
            if ((Ressources::SACREMENT_PREPARE[$inscription->groupe?->niveau] ?? null) === $sacrement->type
                && substr((string) $sacrement->date_sacrement, 0, 10) >= substr((string) $inscription->date_inscription, 0, 10)) {
                $inscription->update(['statut' => 'admis']);
            }
        }
    }

    /** Inverse : une inscription saisie après le sacrement (envoi groupé d'un appareil) est tout de suite « admis ». */
    private static function admettreInscription(Fiche $inscription): void
    {
        if ($inscription->statut !== 'inscrit') {
            return;
        }
        $type = Ressources::SACREMENT_PREPARE[$inscription->groupe?->niveau] ?? null;
        if ($type && Sacrement::where('fidele_id', $inscription->fidele_id)->where('type', $type)->where('date_sacrement', '>=', substr((string) $inscription->date_inscription, 0, 10))->exists()) {
            $inscription->update(['statut' => 'admis']);
        }
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

        if (in_array($cle, ['catechistes', 'groupes', 'inscriptions', 'seances'], true)) {
            self::verifierCatechese($cle, $a, $refs, $user, $existant, $erreur);
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

    private static function verifierCatechese(string $cle, array $a, array $refs, User $user, ?Fiche $existant, callable $erreur): void
    {
        $secteurAttendu = fn ($fiche) => $user->estLimiteAUnSecteur() && $fiche->secteur_id !== $user->secteur_id;

        if ($cle === 'catechistes' && ! empty($refs['fidele_uuid'])) {
            $secteurId = $a['secteur_id'] ?? $existant?->secteur_id;
            if ($refs['fidele_uuid']->secteur_id !== $secteurId) {
                $erreur('fidele_uuid', 'Ce fidèle n\'appartient pas au secteur du catéchiste.');
            }
        }

        if ($cle === 'groupes') {
            $annee = $a['annee'] ?? $existant?->annee;
            [$debut, $fin] = array_map('intval', explode('-', $annee));
            if ($fin !== $debut + 1) {
                $erreur('annee', 'L\'année pastorale doit couvrir deux années qui se suivent (ex. 2026-2027).');
            }
            $secteurId = $a['secteur_id'] ?? $existant?->secteur_id;
            if (! empty($refs['catechiste_uuid']) && $refs['catechiste_uuid']->secteur_id !== $secteurId) {
                $erreur('catechiste_uuid', 'Ce catéchiste appartient à un autre secteur.');
            }
        }

        if ($cle === 'inscriptions') {
            $fidele = $refs['fidele_uuid'] ?? $existant?->fidele;
            $groupe = $refs['groupe_uuid'] ?? $existant?->groupe;
            if ($secteurAttendu($fidele)) {
                $erreur('fidele_uuid', 'Ce fidèle ne fait pas partie de votre secteur.');
            }
            if ($fidele->secteur_id !== $groupe->secteur_id) {
                $erreur('groupe_uuid', 'Ce groupe est dans un autre secteur que le fidèle.');
            }
            if ($fidele->statut !== 'actif' && ! $existant) {
                $erreur('fidele_uuid', 'Seul un fidèle actif peut être inscrit.');
            }
            // Un seul groupe par année pastorale
            $autre = Inscription::where('fidele_id', $fidele->id)->when($existant, fn ($q) => $q->where('id', '!=', $existant->id))
                ->whereHas('groupe', fn ($q) => $q->where('annee', $groupe->annee))->first();
            if ($autre) {
                $erreur('groupe_uuid', $fidele->nomComplet().' est déjà inscrit en '.$groupe->annee.' ('.$autre->groupe->nom.').');
            }
            $date = $a['date_inscription'] ?? $existant?->date_inscription;
            if ($date && $date > now()->addDay()->toDateString()) {
                $erreur('date_inscription', 'La date d\'inscription est dans le futur.');
            }
        }

        if ($cle === 'seances') {
            $groupe = $refs['groupe_uuid'] ?? $existant?->groupe;
            if ($secteurAttendu($groupe)) {
                $erreur('groupe_uuid', 'Ce groupe ne fait pas partie de votre secteur.');
            }
            if (! empty($refs['catechiste_uuid']) && $refs['catechiste_uuid']->secteur_id !== $groupe->secteur_id) {
                $erreur('catechiste_uuid', 'Ce catéchiste appartient à un autre secteur.');
            }
            $date = $a['date_seance'] ?? $existant?->date_seance;
            if ($date && $date > now()->addDay()->toDateString()) {
                $erreur('date_seance', 'La date de la séance est dans le futur.');
            }
        }
    }

    private static function refuser(string $message): never
    {
        throw ValidationException::withMessages(['droit' => $message]);
    }
}
