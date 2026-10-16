<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Synchronisation avec les applications téléphone / ordinateur.
 *
 * - uuid     : identifiant commun à tous les appareils (créé hors ligne par l'appareil)
 * - revision : numéro croissant attribué à chaque écriture sur le serveur ;
 *              un appareil demande « tout ce qui a changé depuis la révision N ».
 */
return new class extends Migration
{
    private const TABLES = [
        'boutique', 'categories', 'fournisseurs', 'produits', 'clients',
        'ventes', 'vente_lignes', 'remboursements', 'mouvements_stock', 'depenses', 'users',
    ];

    public function up(): void
    {
        Schema::create('sync_compteur', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('valeur')->default(0);
        });
        DB::table('sync_compteur')->insert(['id' => 1, 'valeur' => 0]);

        Schema::create('appareils', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nom');
            $table->string('code', 10)->unique(); // préfixe des numéros de ticket : A1-000012
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('derniere_synchro')->nullable();
            $table->timestamps();
        });

        Schema::create('suppressions', function (Blueprint $table) {
            $table->id();
            $table->string('table_nom');
            $table->uuid('uuid');
            $table->unsignedBigInteger('revision')->index();
            $table->timestamps();
        });

        foreach (self::TABLES as $nom) {
            Schema::table($nom, function (Blueprint $table) {
                $table->uuid('uuid')->nullable();
                $table->unsignedBigInteger('revision')->default(0)->index();
            });

            $revision = 0;
            foreach (DB::table($nom)->orderBy('id')->pluck('id') as $id) {
                DB::table($nom)->where('id', $id)->update(['uuid' => (string) Str::uuid(), 'revision' => ++$revision + (int) DB::table('sync_compteur')->value('valeur')]);
            }
            DB::table('sync_compteur')->where('id', 1)->increment('valeur', $revision);

            Schema::table($nom, function (Blueprint $table) {
                $table->unique('uuid');
            });
        }

        Schema::table('ventes', function (Blueprint $table) {
            $table->foreignId('appareil_id')->nullable()->constrained('appareils')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('appareil_id');
        });
        foreach (self::TABLES as $nom) {
            Schema::table($nom, function (Blueprint $table) {
                $table->dropUnique(['uuid']);
                $table->dropIndex(['revision']);
                $table->dropColumn(['uuid', 'revision']);
            });
        }
        Schema::dropIfExists('suppressions');
        Schema::dropIfExists('appareils');
        Schema::dropIfExists('sync_compteur');
    }
};
