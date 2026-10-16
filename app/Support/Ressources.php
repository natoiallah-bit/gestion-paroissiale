<?php

namespace App\Support;

use App\Models\Activite;
use App\Models\Annonce;
use App\Models\Ceb;
use App\Models\Depot;
use App\Models\Fidele;
use App\Models\Mouvement;
use App\Models\Operation;
use App\Models\Sacrement;
use App\Models\Secteur;
use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * Description unique des fiches de la gestion paroissiale.
 *
 * Elle sert à trois endroits, pour que le site et les applications restent identiques :
 *  - les pages du site (liste, formulaire, fiche) ;
 *  - la validation des données reçues des applications (synchronisation) ;
 *  - l'envoi des données aux applications.
 * (Le fichier application/src/ressources.js en est le miroir côté application.)
 *
 * Types de champ : texte, long (texte long), tel, date, heure, entier, montant, choix, ref.
 * Un champ « ref » pointe une autre fiche : sa valeur est l'uuid de cette fiche, stocké
 * dans la colonne « <nom sans _uuid>_id ».
 *
 * portee : 'aucune' (fiche commune), 'obligatoire' (rattachée à un secteur),
 *          'facultative' (secteur ou tout le vicariat si vide),
 *          'centrale' (secteur, ou caisse centrale du vicariat si vide : réservée à ceux qui voient tout le vicariat).
 */
class Ressources
{
    public const ROLES_ECRITURE_PASTORAL = ['admin', 'secretaire'];

    public const SACREMENTS = [
        'bapteme' => 'Baptême',
        'eucharistie' => 'Première communion',
        'confirmation' => 'Confirmation',
        'mariage' => 'Mariage',
        'obseques' => 'Obsèques',
    ];

    public const CATEGORIES_RECETTES = ['Cotisation de CEB', 'Quête', 'Denier du culte', 'Intention de messe', 'Don', 'Offrande de sacrement', 'Autre recette'];

    public const CATEGORIES_DEPENSES = ['Entretien / travaux', 'Électricité / eau', 'Salaires et indemnités', 'Liturgie et culte', 'Transport', 'Aide aux familles', 'Contribution au doyenné / archidiocèse', 'Autre dépense'];

    public static function toutes(): array
    {
        static $definitions;

        return $definitions ??= [
            'secteurs' => [
                'modele' => Secteur::class,
                'libelle' => 'Secteurs', 'singulier' => 'secteur', 'article' => 'un',
                'sous_titre' => 'Chaque secteur regroupe des CEB, des mouvements et des pastorales',
                'portee' => 'aucune',
                'lecture' => ['admin', 'secretaire', 'tresorier'], 'ecriture' => ['admin'],
                'tri' => 'nom',
                'recherche' => ['nom', 'responsable'],
                'champs' => [
                    ['nom' => 'nom', 'label' => 'Nom du secteur', 'type' => 'texte', 'requis' => true, 'max' => 150, 'liste' => true],
                    ['nom' => 'responsable', 'label' => 'Responsable du secteur', 'type' => 'texte', 'max' => 150, 'liste' => true],
                    ['nom' => 'telephone', 'label' => 'Téléphone', 'type' => 'tel', 'max' => 30, 'liste' => true],
                    ['nom' => 'adresse', 'label' => 'Quartiers couverts / adresse', 'type' => 'texte', 'max' => 255],
                ],
                'liees' => [
                    ['ressource' => 'cebs', 'cle' => 'secteur_uuid', 'titre' => 'CEB du secteur'],
                    ['ressource' => 'mouvements', 'cle' => 'secteur_uuid', 'titre' => 'Mouvements et pastorales du secteur'],
                    ['ressource' => 'depots', 'cle' => 'secteur_uuid', 'titre' => 'Dépôts au vicariat'],
                ],
            ],
            'cebs' => [
                'modele' => Ceb::class,
                'libelle' => 'CEB', 'singulier' => 'CEB', 'article' => 'une',
                'sous_titre' => 'Communautés ecclésiales de base',
                'portee' => 'obligatoire',
                'lecture' => ['admin', 'secretaire', 'tresorier'], 'ecriture' => self::ROLES_ECRITURE_PASTORAL,
                'tri' => 'nom',
                'recherche' => ['nom', 'quartier', 'responsable'],
                'champs' => [
                    ['nom' => 'secteur_uuid', 'label' => 'Secteur', 'type' => 'ref', 'ref' => 'secteurs', 'requis' => true, 'liste' => true],
                    ['nom' => 'nom', 'label' => 'Nom de la CEB', 'type' => 'texte', 'requis' => true, 'max' => 150, 'liste' => true],
                    ['nom' => 'quartier', 'label' => 'Quartier', 'type' => 'texte', 'max' => 150, 'liste' => true],
                    ['nom' => 'responsable', 'label' => 'Responsable', 'type' => 'texte', 'max' => 150, 'liste' => true],
                    ['nom' => 'telephone', 'label' => 'Téléphone', 'type' => 'tel', 'max' => 30],
                ],
                'liees' => [
                    ['ressource' => 'fideles', 'cle' => 'ceb_uuid', 'titre' => 'Fidèles de la CEB'],
                    ['ressource' => 'operations', 'cle' => 'ceb_uuid', 'titre' => 'Compte de la CEB au conseil économique du secteur'],
                ],
                'compte' => ['fideles' => 'ceb_uuid'],
            ],
            'mouvements' => [
                'modele' => Mouvement::class,
                'libelle' => 'Mouvements et pastorales', 'singulier' => 'mouvement ou pastorale', 'article' => 'un',
                'sous_titre' => 'Mouvements des jeunes, des adultes, pastorales et services',
                'portee' => 'facultative',
                'lecture' => ['admin', 'secretaire', 'tresorier'], 'ecriture' => self::ROLES_ECRITURE_PASTORAL,
                'tri' => 'nom',
                'recherche' => ['nom', 'responsable'],
                'champs' => [
                    ['nom' => 'secteur_uuid', 'label' => 'Secteur', 'type' => 'ref', 'ref' => 'secteurs', 'liste' => true, 'aide' => 'Vide = mouvement ou pastorale de tout le vicariat.'],
                    ['nom' => 'nom', 'label' => 'Nom', 'type' => 'texte', 'requis' => true, 'max' => 150, 'liste' => true],
                    ['nom' => 'type', 'label' => 'Genre', 'type' => 'choix', 'requis' => true, 'liste' => true,
                        'options' => ['jeunes' => 'Mouvement des jeunes', 'adultes' => 'Mouvement des adultes', 'pastorale' => 'Pastorale', 'service' => 'Service']],
                    ['nom' => 'responsable', 'label' => 'Responsable', 'type' => 'texte', 'max' => 150, 'liste' => true],
                    ['nom' => 'telephone', 'label' => 'Téléphone', 'type' => 'tel', 'max' => 30],
                    ['nom' => 'nombre_membres', 'label' => 'Nombre de membres', 'type' => 'entier', 'requis' => true, 'liste' => true, 'defaut' => 0],
                    ['nom' => 'note', 'label' => 'Note', 'type' => 'long', 'max' => 1000],
                ],
            ],
            'fideles' => [
                'modele' => Fidele::class,
                'libelle' => 'Fidèles', 'singulier' => 'fidèle', 'article' => 'un',
                'portee' => 'obligatoire',
                'lecture' => ['admin', 'secretaire'], 'ecriture' => self::ROLES_ECRITURE_PASTORAL,
                'tri' => 'nom',
                'recherche' => ['nom', 'prenoms', 'telephone', 'famille'],
                'champs' => [
                    ['nom' => 'secteur_uuid', 'label' => 'Secteur', 'type' => 'ref', 'ref' => 'secteurs', 'requis' => true],
                    ['nom' => 'ceb_uuid', 'label' => 'CEB', 'type' => 'ref', 'ref' => 'cebs', 'liste' => true, 'meme_secteur' => true],
                    ['nom' => 'nom', 'label' => 'Nom', 'type' => 'texte', 'requis' => true, 'max' => 100, 'liste' => true],
                    ['nom' => 'prenoms', 'label' => 'Prénoms', 'type' => 'texte', 'requis' => true, 'max' => 150, 'liste' => true],
                    ['nom' => 'sexe', 'label' => 'Sexe', 'type' => 'choix', 'requis' => true, 'liste' => true, 'options' => ['M' => 'Masculin', 'F' => 'Féminin']],
                    ['nom' => 'date_naissance', 'label' => 'Date de naissance', 'type' => 'date'],
                    ['nom' => 'telephone', 'label' => 'Téléphone', 'type' => 'tel', 'max' => 30, 'liste' => true],
                    ['nom' => 'profession', 'label' => 'Profession', 'type' => 'texte', 'max' => 120],
                    ['nom' => 'famille', 'label' => 'Famille / ménage', 'type' => 'texte', 'max' => 150, 'aide' => 'Même texte pour les membres d\'une même famille (ex. Famille Koné).'],
                    ['nom' => 'statut', 'label' => 'Situation', 'type' => 'choix', 'requis' => true, 'liste' => true, 'defaut' => 'actif',
                        'options' => ['actif' => 'Actif', 'decede' => 'Décédé', 'transfere' => 'Transféré']],
                    ['nom' => 'note', 'label' => 'Note', 'type' => 'long', 'max' => 1000],
                ],
                'liees' => [
                    ['ressource' => 'sacrements', 'cle' => 'fidele_uuid', 'titre' => 'Sacrements reçus'],
                ],
            ],
            'sacrements' => [
                'modele' => Sacrement::class,
                'libelle' => 'Sacrements', 'singulier' => 'sacrement', 'article' => 'un',
                'sous_titre' => 'Registres de baptême, communion, confirmation, mariage, obsèques',
                'portee' => 'derivee', // le secteur est celle du fidèle
                'lecture' => ['admin', 'secretaire'], 'ecriture' => self::ROLES_ECRITURE_PASTORAL,
                'tri' => '-date_sacrement',
                'recherche' => ['numero_registre', 'lieu', 'ministre'],
                'champs' => [
                    ['nom' => 'fidele_uuid', 'label' => 'Fidèle', 'type' => 'ref', 'ref' => 'fideles', 'requis' => true, 'liste' => true],
                    ['nom' => 'type', 'label' => 'Sacrement', 'type' => 'choix', 'requis' => true, 'liste' => true, 'options' => self::SACREMENTS],
                    ['nom' => 'date_sacrement', 'label' => 'Date', 'type' => 'date', 'requis' => true, 'liste' => true],
                    ['nom' => 'lieu', 'label' => 'Lieu', 'type' => 'texte', 'max' => 150, 'liste' => true],
                    ['nom' => 'ministre', 'label' => 'Ministre (prêtre)', 'type' => 'texte', 'max' => 150],
                    ['nom' => 'parrain', 'label' => 'Parrain / marraine / témoins', 'type' => 'texte', 'max' => 255],
                    ['nom' => 'conjoint', 'label' => 'Conjoint (mariage)', 'type' => 'texte', 'max' => 200],
                    ['nom' => 'numero_registre', 'label' => 'N° du registre', 'type' => 'texte', 'max' => 60, 'liste' => true, 'aide' => 'Numéro ou folio dans le registre papier.'],
                    ['nom' => 'note', 'label' => 'Note', 'type' => 'long', 'max' => 1000],
                ],
            ],
            'operations' => [
                'modele' => Operation::class,
                'libelle' => 'Comptes et opérations', 'singulier' => 'opération', 'article' => 'une',
                'sous_titre' => 'Compte de chaque CEB au conseil économique du secteur, compte du secteur, caisse du vicariat',
                'portee' => 'centrale', // secteur vide = caisse centrale du vicariat
                'lecture' => ['admin', 'tresorier'], 'ecriture' => ['admin', 'tresorier'],
                'tri' => '-date_operation',
                'recherche' => ['libelle', 'categorie'],
                'champs' => [
                    ['nom' => 'secteur_uuid', 'label' => 'Secteur', 'type' => 'ref', 'ref' => 'secteurs', 'liste' => true, 'aide' => 'Vide = caisse centrale du vicariat.'],
                    ['nom' => 'ceb_uuid', 'label' => 'Compte de la CEB', 'type' => 'ref', 'ref' => 'cebs', 'liste' => true, 'meme_secteur' => true, 'aide' => 'Vide = compte du secteur lui-même.'],
                    ['nom' => 'sens', 'label' => 'Sens', 'type' => 'choix', 'requis' => true, 'liste' => true, 'options' => ['recette' => 'Dépôt / recette', 'depense' => 'Retrait / dépense']],
                    ['nom' => 'categorie', 'label' => 'Catégorie', 'type' => 'choix', 'requis' => true, 'liste' => true,
                        'options' => array_combine(array_merge(self::CATEGORIES_RECETTES, self::CATEGORIES_DEPENSES), array_merge(self::CATEGORIES_RECETTES, self::CATEGORIES_DEPENSES))],
                    ['nom' => 'libelle', 'label' => 'Libellé', 'type' => 'texte', 'requis' => true, 'max' => 200, 'liste' => true],
                    ['nom' => 'montant', 'label' => 'Montant (FCFA)', 'type' => 'montant', 'requis' => true, 'liste' => true],
                    ['nom' => 'date_operation', 'label' => 'Date', 'type' => 'date', 'requis' => true, 'liste' => true],
                    ['nom' => 'note', 'label' => 'Note', 'type' => 'long', 'max' => 1000],
                ],
            ],
            'depots' => [
                'modele' => Depot::class,
                'libelle' => 'Dépôts au vicariat', 'singulier' => 'dépôt', 'article' => 'un',
                'sous_titre' => 'Chaque secteur dépose son argent à la caisse centrale ; les cumuls sont recalculés à chaque dépôt',
                'portee' => 'obligatoire',
                'lecture' => ['admin', 'tresorier'], 'ecriture' => ['admin', 'tresorier'],
                'tri' => '-date_depot',
                'recherche' => ['reference', 'deposant'],
                'champs' => [
                    ['nom' => 'secteur_uuid', 'label' => 'Secteur', 'type' => 'ref', 'ref' => 'secteurs', 'requis' => true, 'liste' => true],
                    ['nom' => 'date_depot', 'label' => 'Date du dépôt', 'type' => 'date', 'requis' => true, 'liste' => true],
                    ['nom' => 'montant', 'label' => 'Montant déposé (FCFA)', 'type' => 'montant', 'requis' => true, 'liste' => true],
                    ['nom' => 'reference', 'label' => 'N° de reçu', 'type' => 'texte', 'max' => 60, 'liste' => true],
                    ['nom' => 'deposant', 'label' => 'Déposé par', 'type' => 'texte', 'max' => 150],
                    ['nom' => 'note', 'label' => 'Note', 'type' => 'long', 'max' => 1000],
                ],
                // Colonnes calculées (jamais saisies) : cumul du secteur et total reçu au vicariat après ce dépôt
                'calculees' => [
                    ['nom' => 'cumul_secteur', 'label' => 'Cumul du secteur', 'type' => 'montant'],
                    ['nom' => 'total_vicariat', 'label' => 'Total au vicariat', 'type' => 'montant', 'central' => true],
                ],
            ],
            'annonces' => [
                'modele' => Annonce::class,
                'libelle' => 'Annonces', 'singulier' => 'annonce', 'article' => 'une',
                'portee' => 'facultative',
                'lecture' => ['admin', 'secretaire', 'tresorier'], 'ecriture' => self::ROLES_ECRITURE_PASTORAL,
                'tri' => '-date_debut',
                'recherche' => ['titre', 'contenu'],
                'champs' => [
                    ['nom' => 'secteur_uuid', 'label' => 'Secteur', 'type' => 'ref', 'ref' => 'secteurs', 'liste' => true, 'aide' => 'Vide = annonce pour tout le vicariat.'],
                    ['nom' => 'titre', 'label' => 'Titre', 'type' => 'texte', 'requis' => true, 'max' => 200, 'liste' => true],
                    ['nom' => 'contenu', 'label' => 'Texte de l\'annonce', 'type' => 'long', 'requis' => true, 'max' => 5000],
                    ['nom' => 'date_debut', 'label' => 'Publiée le', 'type' => 'date', 'requis' => true, 'liste' => true],
                    ['nom' => 'date_fin', 'label' => 'Valable jusqu\'au', 'type' => 'date', 'liste' => true, 'aide' => 'Vide = sans limite.'],
                ],
            ],
            'activites' => [
                'modele' => Activite::class,
                'libelle' => 'Programme des activités', 'singulier' => 'activité', 'article' => 'une',
                'sous_titre' => 'Vie vicariale et paroissiale',
                'portee' => 'facultative',
                'lecture' => ['admin', 'secretaire', 'tresorier'], 'ecriture' => self::ROLES_ECRITURE_PASTORAL,
                'tri' => 'date_activite',
                'recherche' => ['titre', 'lieu'],
                'champs' => [
                    ['nom' => 'secteur_uuid', 'label' => 'Secteur', 'type' => 'ref', 'ref' => 'secteurs', 'liste' => true, 'aide' => 'Vide = activité de tout le vicariat.'],
                    ['nom' => 'titre', 'label' => 'Activité', 'type' => 'texte', 'requis' => true, 'max' => 200, 'liste' => true],
                    ['nom' => 'date_activite', 'label' => 'Date', 'type' => 'date', 'requis' => true, 'liste' => true],
                    ['nom' => 'heure', 'label' => 'Heure', 'type' => 'heure', 'liste' => true],
                    ['nom' => 'lieu', 'label' => 'Lieu', 'type' => 'texte', 'max' => 150, 'liste' => true],
                    ['nom' => 'mouvement_uuid', 'label' => 'Organisée par', 'type' => 'ref', 'ref' => 'mouvements'],
                    ['nom' => 'description', 'label' => 'Détails', 'type' => 'long', 'max' => 2000],
                ],
            ],
        ];
    }

    public static function cles(): array
    {
        return array_keys(self::toutes());
    }

    public static function get(string $cle): array
    {
        return self::toutes()[$cle] ?? abort(404);
    }

    public static function existe(string $cle): bool
    {
        return isset(self::toutes()[$cle]);
    }

    public static function champ(string $cle, string $nom): ?array
    {
        foreach (self::get($cle)['champs'] as $champ) {
            if ($champ['nom'] === $nom) {
                return $champ;
            }
        }

        return null;
    }

    /** Colonne de la base pour un champ : secteur_uuid → secteur_id */
    public static function colonne(array $champ): string
    {
        return $champ['type'] === 'ref' ? substr($champ['nom'], 0, -5).'_id' : $champ['nom'];
    }

    /** Nom de la relation Eloquent pour un champ ref : secteur_uuid → secteur */
    public static function relation(array $champ): string
    {
        return substr($champ['nom'], 0, -5);
    }

    public static function peutLire(?User $user, string $cle): bool
    {
        return $user && in_array($user->role, self::get($cle)['lecture'], true);
    }

    public static function peutEcrire(?User $user, string $cle): bool
    {
        return $user && in_array($user->role, self::get($cle)['ecriture'], true);
    }

    /** Règles de validation d'un champ (formulaire du site et synchronisation) */
    public static function regles(string $cle, ?int $ignorerId = null): array
    {
        $regles = [];
        foreach (self::get($cle)['champs'] as $champ) {
            $r = [$champ['requis'] ?? false ? 'required' : 'nullable'];
            $r = array_merge($r, match ($champ['type']) {
                'texte', 'tel' => ['string', 'max:'.($champ['max'] ?? 255)],
                'long' => ['string', 'max:'.($champ['max'] ?? 2000)],
                'date' => ['date'],
                'heure' => ['date_format:H:i'],
                'entier' => ['integer', 'min:0', 'max:10000000'],
                'montant' => ['integer', 'min:1', 'max:100000000000'],
                'choix' => [Rule::in(array_keys($champ['options']))],
                'ref' => ['uuid', 'exists:'.$champ['ref'].',uuid'],
            });
            $regles[$champ['nom']] = $r;
        }

        return $regles;
    }

    /** Valeurs d'une fiche telles qu'envoyées aux applications */
    public static function exporter(string $cle, $fiche): array
    {
        $donnees = ['uuid' => $fiche->uuid];
        foreach (self::get($cle)['champs'] as $champ) {
            if ($champ['type'] === 'ref') {
                $donnees[$champ['nom']] = $fiche->{self::relation($champ)}?->uuid;
            } elseif ($champ['type'] === 'date') {
                $donnees[$champ['nom']] = $fiche->{$champ['nom']} ? substr((string) $fiche->{$champ['nom']}, 0, 10) : null;
            } else {
                $donnees[$champ['nom']] = $fiche->{$champ['nom']};
            }
        }
        if ($cle === 'sacrements') {
            $donnees['secteur_uuid'] = $fiche->secteur?->uuid;
        }

        return $donnees;
    }

    /** Description envoyée telle quelle aux applications (même source que le site) */
    public static function pourApplication(): string
    {
        $ressources = [];
        foreach (self::toutes() as $cle => $def) {
            unset($def['modele']);
            $ressources[$cle] = $def;
        }

        return json_encode([
            'ressources' => $ressources,
            'sacrements' => self::SACREMENTS,
            'categories_recettes' => self::CATEGORIES_RECETTES,
            'categories_depenses' => self::CATEGORIES_DEPENSES,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
    }
}
