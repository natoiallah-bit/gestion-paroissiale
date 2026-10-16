<?php

use App\Http\Controllers\AppareilController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ParametreController;
use App\Http\Controllers\RapportController;
use App\Http\Controllers\RessourceController;
use App\Http\Controllers\UserController;
use App\Support\Ressources;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth', 'actif'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/rapports', [RapportController::class, 'index'])->name('rapports.index');

    // Toutes les fiches (paroisses, cebs, fideles, sacrements…) : les droits sont vérifiés
    // dans le contrôleur, d'après la description de App\Support\Ressources
    Route::get('/sacrements/{id}/extrait', [RessourceController::class, 'extrait'])->whereNumber('id')->name('sacrements.extrait');
    foreach (Ressources::cles() as $cle) {
        Route::get("/$cle", [RessourceController::class, 'index'])->defaults('ressource', $cle)->name("$cle.index");
        Route::get("/$cle/nouveau", [RessourceController::class, 'create'])->defaults('ressource', $cle)->name("$cle.create");
        Route::post("/$cle", [RessourceController::class, 'store'])->defaults('ressource', $cle)->name("$cle.store");
        Route::get("/$cle/{id}", [RessourceController::class, 'show'])->whereNumber('id')->defaults('ressource', $cle)->name("$cle.show");
        Route::get("/$cle/{id}/modifier", [RessourceController::class, 'edit'])->whereNumber('id')->defaults('ressource', $cle)->name("$cle.edit");
        Route::put("/$cle/{id}", [RessourceController::class, 'update'])->whereNumber('id')->defaults('ressource', $cle)->name("$cle.update");
        Route::delete("/$cle/{id}", [RessourceController::class, 'destroy'])->whereNumber('id')->defaults('ressource', $cle)->name("$cle.destroy");
    }

    Route::middleware('admin')->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::get('/appareils', [AppareilController::class, 'index'])->name('appareils.index');
        Route::post('/appareils/{appareil}/revoquer', [AppareilController::class, 'revoquer'])->name('appareils.revoquer');
        Route::get('/parametres', [ParametreController::class, 'edit'])->name('parametres.edit');
        Route::put('/parametres', [ParametreController::class, 'update'])->name('parametres.update');
    });
});
