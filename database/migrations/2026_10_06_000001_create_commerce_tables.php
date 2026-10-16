<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tous les montants sont des entiers en FCFA (pas de centimes).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boutique', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->default('Ma Boutique');
            $table->string('adresse')->nullable();
            $table->string('telephone')->nullable();
            $table->string('pied_ticket')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->timestamps();
        });

        Schema::create('fournisseurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('telephone')->nullable();
            $table->string('adresse')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('produits', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('code')->nullable()->unique();
            $table->foreignId('categorie_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('unite')->default('pièce');
            $table->unsignedInteger('prix_achat')->default(0);
            $table->unsignedInteger('prix_vente');
            $table->integer('stock')->default(0);
            $table->unsignedInteger('seuil_alerte')->default(5);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('telephone')->nullable();
            $table->string('adresse')->nullable();
            $table->timestamps();
        });

        Schema::create('ventes', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedInteger('total');
            $table->unsignedInteger('montant_paye');
            // especes | mobile_money (mode du montant encaissé ; le reste éventuel est un crédit)
            $table->string('mode_paiement')->default('especes');
            $table->string('statut')->default('validee'); // validee | annulee
            $table->foreignId('annulee_par')->nullable()->constrained('users');
            $table->timestamp('annulee_le')->nullable();
            $table->string('motif_annulation')->nullable();
            $table->timestamps();
            $table->index(['statut', 'created_at']);
        });

        Schema::create('vente_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vente_id')->constrained('ventes')->cascadeOnDelete();
            $table->foreignId('produit_id')->constrained('produits');
            $table->string('designation');
            $table->unsignedInteger('quantite');
            $table->unsignedInteger('prix_unitaire');
            // Prix d'achat au moment de la vente : sert à calculer la marge
            $table->unsignedInteger('prix_achat_unitaire');
            $table->unsignedInteger('total');
        });

        Schema::create('remboursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedInteger('montant');
            $table->string('mode_paiement')->default('especes');
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('mouvements_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produit_id')->constrained('produits')->cascadeOnDelete();
            // entree | ajustement | vente | annulation
            $table->string('type');
            $table->integer('quantite'); // positif = entrée, négatif = sortie
            $table->integer('stock_apres');
            $table->unsignedInteger('prix_achat_unitaire')->nullable();
            $table->foreignId('fournisseur_id')->nullable()->constrained('fournisseurs')->nullOnDelete();
            $table->foreignId('vente_id')->nullable()->constrained('ventes')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('depenses', function (Blueprint $table) {
            $table->id();
            $table->string('libelle');
            $table->string('categorie')->default('Autre');
            $table->unsignedInteger('montant');
            $table->date('date_depense');
            $table->foreignId('fournisseur_id')->nullable()->constrained('fournisseurs')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depenses');
        Schema::dropIfExists('mouvements_stock');
        Schema::dropIfExists('remboursements');
        Schema::dropIfExists('vente_lignes');
        Schema::dropIfExists('ventes');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('produits');
        Schema::dropIfExists('fournisseurs');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('boutique');
    }
};
