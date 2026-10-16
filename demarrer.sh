#!/usr/bin/env sh
# Lance le serveur sur Linux / Mac (sur Windows : demarrer.bat)
cd "$(dirname "$0")"
php artisan migrate --force >/dev/null
php artisan paroisse:adresses --port=8200
echo "  Laissez ce terminal ouvert pendant que la paroisse travaille."
PHP_CLI_SERVER_WORKERS=4 php artisan serve --host=0.0.0.0 --port=8200 --no-reload
