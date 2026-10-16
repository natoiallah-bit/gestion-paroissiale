<?php

use App\Http\Controllers\AppareilController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CaisseController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepenseController;
use App\Http\Controllers\FournisseurController;
use App\Http\Controllers\ParametreController;
use App\Http\Controllers\ProduitController;
use App\Http\Controllers\RapportController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VenteController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth', 'actif'])->group(function () {
    // Gérant : tableau de bord ; vendeur : directement la caisse
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Accessible à tous les comptes
    Route::get('/caisse', [CaisseController::class, 'index'])->name('caisse.index');
    Route::post('/caisse', [CaisseController::class, 'store'])->name('caisse.store');
    Route::get('/ventes', [VenteController::class, 'index'])->name('ventes.index');
    Route::get('/ventes/{vente}', [VenteController::class, 'show'])->name('ventes.show');
    Route::get('/ventes/{vente}/ticket', [VenteController::class, 'ticket'])->name('ventes.ticket');

    Route::resource('clients', ClientController::class)->except(['destroy']);
    Route::post('/clients/{client}/remboursements', [ClientController::class, 'rembourser'])->name('clients.rembourser');

    Route::middleware('gerant')->group(function () {
        Route::post('/ventes/{vente}/annuler', [VenteController::class, 'annuler'])->name('ventes.annuler');
        Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');

        Route::resource('produits', ProduitController::class);
        Route::get('/stock/entree', [StockController::class, 'entreeForm'])->name('stock.entree');
        Route::post('/stock/entree', [StockController::class, 'entree'])->name('stock.entree.store');
        Route::post('/produits/{produit}/ajustement', [StockController::class, 'ajuster'])->name('stock.ajuster');
        Route::get('/stock/mouvements', [StockController::class, 'mouvements'])->name('stock.mouvements');

        Route::get('/categories', [CategorieController::class, 'index'])->name('categories.index');
        Route::post('/categories', [CategorieController::class, 'store'])->name('categories.store');
        Route::put('/categories/{categorie}', [CategorieController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{categorie}', [CategorieController::class, 'destroy'])->name('categories.destroy');

        Route::resource('fournisseurs', FournisseurController::class)->except(['show']);

        Route::get('/depenses', [DepenseController::class, 'index'])->name('depenses.index');
        Route::post('/depenses', [DepenseController::class, 'store'])->name('depenses.store');
        Route::delete('/depenses/{depense}', [DepenseController::class, 'destroy'])->name('depenses.destroy');

        Route::get('/rapports', [RapportController::class, 'index'])->name('rapports.index');

        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::get('/appareils', [AppareilController::class, 'index'])->name('appareils.index');
        Route::post('/appareils/{appareil}/revoquer', [AppareilController::class, 'revoquer'])->name('appareils.revoquer');
        Route::get('/parametres', [ParametreController::class, 'edit'])->name('parametres.edit');
        Route::put('/parametres', [ParametreController::class, 'update'])->name('parametres.update');
    });
});
