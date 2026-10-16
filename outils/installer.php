<?php

/**
 * Installation et mise à jour de Gestion Paroissiale (appelé par installer.bat / installer.sh).
 * Chaque étape explique quoi faire si elle échoue. Tout est aussi écrit dans installation.log.
 */
chdir(dirname(__DIR__));
$journal = fopen('installation.log', 'w');

function dire(string $texte): void
{
    global $journal;
    echo $texte.PHP_EOL;
    fwrite($journal, $texte.PHP_EOL);
}

function echec(string $texte): never
{
    dire('');
    dire('*** PROBLEME : '.$texte);
    dire('');
    dire("Envoyez une photo de cette fenetre (ou le fichier installation.log) pour obtenir de l'aide.");
    exit(1);
}

function lancer(string $commande): bool
{
    dire('> '.$commande);
    passthru($commande.' 2>&1', $code);

    return $code === 0;
}

$php = escapeshellarg(PHP_BINARY);

dire('=== Installation de Gestion Paroissiale ===');
dire('Dossier : '.getcwd());
dire('PHP     : '.PHP_BINARY.' (version '.PHP_VERSION.')');
dire('php.ini : '.(php_ini_loaded_file() ?: 'aucun'));
dire('');

// 1. Version de PHP
dire('[1/6] Verification de PHP...');
if (version_compare(PHP_VERSION, '8.3.0', '<')) {
    echec('PHP '.PHP_VERSION.' est trop ancien : il faut PHP 8.3 ou plus recent.');
}

// 2. Extensions nécessaires
dire('[2/6] Verification des extensions PHP...');
$manquantes = array_values(array_filter(
    ['pdo_sqlite', 'sqlite3', 'mbstring', 'openssl', 'fileinfo', 'curl'],
    fn ($e) => ! extension_loaded($e)
));
if ($manquantes) {
    echec('extensions PHP desactivees : '.implode(', ', $manquantes).".\n"
        .'    Ouvrez '.(php_ini_loaded_file() ?: 'le fichier php.ini')." avec le Bloc-notes,\n"
        ."    cherchez ces lignes et enlevez le point-virgule au debut :\n"
        .implode("\n", array_map(fn ($e) => "        extension=$e", $manquantes))."\n"
        .(php_ini_loaded_file() ? '' : "    (pas de php.ini : copiez php.ini-development en php.ini dans le dossier de PHP)\n")
        .'    Enregistrez, puis relancez installer.bat');
}

// 3. Les fichiers du projet sont-ils complets ?
if (! is_file('composer.json') || ! is_file('artisan')) {
    echec("les fichiers du projet sont incomplets.\n"
        ."    Si vous avez telecharge le ZIP : faites clic droit > Extraire tout, puis lancez installer.bat\n"
        .'    depuis le dossier extrait (pas depuis l\'apercu du ZIP).');
}

// 4. Composer : installé sur l'ordinateur, sinon téléchargé dans le dossier du projet
dire('[3/6] Installation des composants (quelques minutes la premiere fois)...');
$sortie = [];
exec((PHP_OS_FAMILY === 'Windows' ? 'where composer' : 'command -v composer').' 2>&1', $sortie, $code);
if ($code === 0) {
    $composer = 'composer';
} else {
    if (! is_file('composer.phar')) {
        dire('    Composer absent : telechargement de composer.phar...');
        if (! @copy('https://getcomposer.org/download/latest-stable/composer.phar', 'composer.phar')) {
            echec("impossible de telecharger Composer (internet necessaire pour l'installation).\n"
                .'    Installez Composer depuis https://getcomposer.org/download/ puis relancez.');
        }
    }
    $composer = $php.' composer.phar';
}
if (! lancer($composer.' install --no-interaction --no-dev --optimize-autoloader')) {
    echec("l'installation des composants a echoue (voir le message juste au-dessus).\n"
        .'    Verifiez la connexion internet, puis relancez installer.bat');
}

// 5. Configuration
dire('[4/6] Configuration...');
if (! is_file('.env')) {
    copy('.env.example', '.env');
}
if (preg_match('/^APP_KEY=\s*$/m', file_get_contents('.env'))) {
    lancer($php.' artisan key:generate --force') || echec('creation de la cle impossible.');
}

// 6. Base de données
dire('[5/6] Base de donnees...');
if (! is_file('database/database.sqlite')) {
    touch('database/database.sqlite');
}
lancer($php.' artisan migrate --force --seed') || echec('creation de la base de donnees impossible.');
lancer($php.' artisan optimize:clear');

dire('[6/6] Termine !');
dire('');
dire('compte administrateur : admin@paroisse.local / changer-ce-mot-de-passe');
dire('(changez le mot de passe apres la premiere connexion : menu Utilisateurs)');
dire('');
dire('Pour lancer l\'application : double-cliquez sur demarrer.bat');
