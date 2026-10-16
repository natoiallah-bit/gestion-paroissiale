<?php

namespace App\Providers;

use App\Models\Boutique;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::defaultView('partials.pagination');
        Paginator::defaultSimpleView('partials.pagination');

        // Nom de la boutique affiché dans toutes les pages (une seule requête par page)
        View::composer(['layouts.app', 'auth.login'], function ($view) {
            static $nom;
            $nom ??= Schema::hasTable('boutique') ? Boutique::courante()->nom : config('app.name');
            $view->with('boutiqueNom', $nom);
        });
    }
}
