<?php

namespace App\Providers;

use App\Models\Paroisse;
use App\Models\Sacrement;
use App\Models\User;
use App\Models\Vicariat;
use App\Support\Ressources;
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

        // Relations « belongsTo » des champs de type ref (paroisse_uuid → ->paroisse, fidele_uuid → ->fidele…)
        foreach (Ressources::toutes() as $def) {
            foreach ($def['champs'] as $champ) {
                if ($champ['type'] !== 'ref') {
                    continue;
                }
                $cible = Ressources::get($champ['ref'])['modele'];
                $def['modele']::resolveRelationUsing(
                    Ressources::relation($champ),
                    fn ($modele) => $modele->belongsTo($cible, Ressources::colonne($champ))
                );
            }
        }
        User::resolveRelationUsing('paroisse', fn ($m) => $m->belongsTo(Paroisse::class, 'paroisse_id'));
        Sacrement::resolveRelationUsing('paroisse', fn ($m) => $m->belongsTo(Paroisse::class, 'paroisse_id'));

        // Nom du vicariat affiché dans toutes les pages (une seule requête par page)
        View::composer(['layouts.app', 'auth.login'], function ($view) {
            static $nom;
            $nom ??= Schema::hasTable('vicariat') ? Vicariat::courant()->nom : config('app.name');
            $view->with('vicariatNom', $nom);
        });
    }
}
