# Gestion du vicariat Christ-Roi de Koundoul Ngama

Archidiocèse de N'Djamena · Doyenné de N'Djamena Sud.

Gestion du vicariat et de ses secteurs, en français, montants en FCFA : fidèles et familles, CEB, mouvements et
pastorales, sacrements, comptes et dépôts, annonces et programme des activités.

## Organisation reprise par l'application

```
Vicariat Christ-Roi de Koundoul Ngama
 └─ Secteurs
     ├─ CEB (communautés ecclésiales de base) ── chacune a un compte au conseil économique du secteur
     ├─ Mouvements des jeunes, des adultes
     └─ Pastorales (liturgique, familiale…) et services
```

**Les comptes, du bas vers le haut :**
1. **CEB** : chaque CEB ouvre un compte au conseil économique du secteur (cotisations, dépôts, retraits).
2. **Secteur** : le secteur compile les comptes de ses CEB et ses propres opérations.
3. **Vicariat** : tous les secteurs **déposent leur argent à la caisse centrale du vicariat**. Chaque dépôt est enregistré
   (date, montant, n° de reçu) et les montants sont **recalculés à chaque dépôt** : cumul déposé par le secteur et total reçu
   par le vicariat. Le conseil économique du vicariat compile l'ensemble : ce que chaque secteur a collecté, déjà déposé et **reste à déposer**.

Même logique que *gestion-de-commerce* — trois parties :

| Partie | Où | Rôle |
|---|---|---|
| **Serveur** (Laravel, ce dossier) | ordinateur du vicariat ou hébergé en ligne | site web + API de synchronisation |
| **Application Android** (`GestionParoissiale.apk`) | téléphones | travail **hors ligne**, se synchronise dès qu'il y a une connexion |
| **Application Windows** (`GestionParoissiale-Installation-….exe`) | ordinateurs | même application, installée sur le PC |

Les applications Android et Windows sont construites à partir du dossier [`application/`](application/).

## Utiliser en local (sur l'ordinateur du vicariat ou du secteur)

Le serveur tourne sur un ordinateur, avec SQLite. Les téléphones s'y connectent par le **Wi-Fi** (pas besoin d'internet, sauf pour l'installation).

Prérequis : **PHP 8.3 ou plus** (Laragon et XAMPP sont trouvés automatiquement). Composer est téléchargé tout seul s'il n'est pas installé.

1. Récupérer le projet (ZIP GitHub → clic droit → Extraire tout, ou `git clone`).
2. Double-cliquer sur **`installer.bat`** (une seule fois, avec internet).
3. Double-cliquer sur **`demarrer.bat`** : le site s'ouvre sur `http://127.0.0.1:8200` et la fenêtre affiche
   l'adresse à taper dans les téléphones, par exemple `192.168.1.20:8200`. **Laisser cette fenêtre ouverte.**
4. Au premier lancement, Windows demande d'autoriser PHP : cocher **Réseaux privés** et autoriser.
5. Nouvelle version : **`mettre-a-jour.bat`**, puis `demarrer.bat`. Linux / Mac : `./installer.sh` puis `./demarrer.sh`.

Le port **8200** diffère de celui des autres gestions (8000 scolaire, 8100 commerce) : elles peuvent tourner ensemble.
Si l'installation échoue, `installer.bat` explique le problème et l'écrit dans `installation.log`.

Toutes les données sont dans `database/database.sqlite` : **copiez ce fichier régulièrement** sur une clé USB.

Compte administrateur créé par défaut : `admin@vicariat.local` / `changer-ce-mot-de-passe`
(modifiable avant l'installation avec `ADMIN_EMAIL` et `ADMIN_PASSWORD` dans `.env`). Changez le mot de passe dès la première connexion.

## Mise en route

1. **Paramètres** : archidiocèse, doyenné, responsable du vicariat (utilisés sur les extraits de registre).
2. **Secteurs** : créer les secteurs du vicariat.
3. **Utilisateurs** : un secrétaire et un trésorier par secteur ; un trésorier **sans secteur** tient la caisse centrale du vicariat.
4. Les secrétaires saisissent CEB, fidèles, sacrements, mouvements, pastorales, annonces et programme.
5. Les trésoriers de secteur ouvrent les comptes des CEB (menu **Comptes et opérations**) et enregistrent les **dépôts au vicariat**.

Pour essayer avec des données d'exemple : `php artisan db:seed --class=DemoSeeder`
(comptes `secretaire.secteur1@vicariat.local`, `tresorier.secteur1@vicariat.local`, `tresorier.vicariat@vicariat.local`… / `demo1234`).

## Qui voit quoi

| Rôle | Accès |
|---|---|
| **Administrateur** (vicariat) | Tout le vicariat : tous les secteurs, utilisateurs, appareils, paramètres, comptes consolidés |
| **Secrétaire** (d'un secteur) | CEB, fidèles, mouvements, pastorales, sacrements, annonces, programme **de son secteur** ; lecture des annonces et activités du vicariat |
| **Trésorier** (d'un secteur) | Comptes des CEB, opérations et dépôts **de son secteur** ; il ne voit ni les autres secteurs ni la caisse centrale |
| **Trésorier du vicariat** (compte sans secteur) | Comptes de tous les secteurs, dépôts reçus, caisse centrale, compilation du vicariat |

Un compte rattaché à un secteur ne voit et ne modifie que ce secteur, sur le site comme dans l'application
(le serveur filtre les données envoyées à chaque appareil).

## Fonctionnalités

| Module | Contenu |
|---|---|
| **Fidèles & familles** | Registre des paroissiens (identité, famille, CEB, situation actif / décédé / transféré), recherche, filtres |
| **CEB** | Communautés ecclésiales de base par secteur, responsable, nombre de fidèles |
| **Mouvements & pastorales** | Mouvements des jeunes, des adultes, pastorales, services : responsable et nombre de membres |
| **Sacrements** | Baptême, première communion, confirmation, mariage, obsèques ; n° de registre ; **extrait de registre imprimable** |
| **Comptes & opérations** | Compte de chaque CEB au conseil économique du secteur, compte du secteur, caisse centrale du vicariat ; cotisations, quêtes, dons, dépenses |
| **Dépôts au vicariat** | Dépôt de chaque secteur à la caisse centrale ; cumul du secteur et total reçu recalculés à chaque dépôt |
| **Annonces** | Pour un secteur ou pour tout le vicariat, avec période de validité |
| **Programme** | Activités de la vie vicariale et paroissiale, avec date, heure, lieu, organisateur |
| **Tableau de bord & rapports** | Effectifs (hommes / femmes / familles), sacrements de l'année, **compilation des comptes CEB → secteur → vicariat** (compilé, déposé, reste à déposer), vue par secteur |

Règles contrôlées partout (site, application, synchronisation) :
- baptême, première communion, confirmation et obsèques ne s'enregistrent **qu'une fois** par fidèle ;
- un sacrement ne peut pas précéder la naissance ni être dans le futur ; des **obsèques passent le fidèle en « décédé »** ;
- la CEB d'un fidèle doit être de son secteur ; la catégorie d'une opération doit correspondre à une recette ou une dépense ;
- un fidèle ayant des sacrements, ou un secteur non vide, ne peut pas être supprimé ;
- supprimer une CEB ou un mouvement détache proprement ses fidèles / activités.

## Télécharger les applications

Onglet **Releases** du dépôt GitHub → dernière version → `GestionParoissiale.apk` (téléphone) ou
`GestionParoissiale-Installation-….exe` (Windows).
Au premier lancement : adresse du serveur (affichée par `demarrer.bat`), e-mail et mot de passe. Ensuite l'application fonctionne **sans internet**.

### Comment marche la synchronisation

- Chaque fiche créée ou modifiée est enregistrée d'abord **dans l'appareil**, puis envoyée au serveur dès que la connexion est là
  (au démarrage, chaque minute, quelques secondes après une saisie, ou avec le bouton « Synchroniser »).
- L'appareil reçoit en retour ce que les autres appareils et le site ont fait.
- Une coupure pendant l'envoi ne crée jamais de doublon (chaque fiche a un identifiant unique créé sur l'appareil).
- Si deux appareils modifient la même fiche hors ligne, la dernière modification reçue gagne.
- Une fiche refusée par le serveur (doublon de sacrement, droit insuffisant…) est signalée dans l'écran **Synchro** avec la raison.
- L'administrateur voit tous les appareils (menu **Appareils**) et peut déconnecter un téléphone perdu.
- Les suppressions se font sur le site.

### Héberger le serveur

N'importe quel hébergement PHP 8.3 avec MySQL ou SQLite convient, en **https**. Il suffit de taper la nouvelle adresse dans les applications.

## Installation pour développer

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

## Une seule description des fiches

Les champs, droits et règles de chaque fiche sont décrits **une seule fois** dans `app/Support/Ressources.php`.
Le site, la validation de la synchronisation et l'application s'appuient dessus. Après l'avoir modifié :

```bash
php artisan vicariat:ressources   # met à jour application/src/ressources.json (un test vérifie qu'il est à jour)
```

## Tests

```bash
php artisan test                 # serveur
cd application && npm test       # logique hors ligne de l'application
```

## Développer l'application

```bash
cd application
npm install
npm run dev        # dans le navigateur
npm run exe        # installateur Windows (sur Windows)
npm run android    # puis ouvrir application/android dans Android Studio
```

GitHub Actions construit automatiquement l'APK et l'EXE à chaque modification du dossier `application/` et les publie dans Releases
sous le numéro de version de `application/package.json` (augmenter ce numéro pour publier une nouvelle version).
