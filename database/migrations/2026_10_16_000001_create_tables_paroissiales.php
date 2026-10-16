<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tables de la gestion paroissiale et vicariale.
 *
 * Chaque table synchronisée a :
 *  - uuid     : identifiant commun à tous les appareils (créé hors ligne par l'appareil)
 *  - revision : numéro croissant attribué à chaque écriture sur le serveur ;
 *               un appareil demande « tout ce qui a changé depuis la révision N ».
 * Tous les montants sont des entiers en FCFA (pas de centimes).
 */
return new class extends Migration
{
    private function synchro(Blueprint $table): void
    {
        $table->uuid('uuid')->unique();
        $table->unsignedBigInteger('revision')->default(0)->index();
    }

    public function up(): void
    {
        Schema::create('sync_compteur', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('valeur')->default(0);
        });
        DB::table('sync_compteur')->insert(['id' => 1, 'valeur' => 0]);

        Schema::create('suppressions', function (Blueprint $table) {
            $table->id();
            $table->string('table_nom');
            $table->uuid('uuid');
            $table->unsignedBigInteger('revision')->index();
            $table->timestamps();
        });

        Schema::create('vicariat', function (Blueprint $table) {
            $table->id();
            $this->synchro($table);
            $table->string('nom')->default('Vicariat');
            $table->string('diocese')->nullable();
            $table->string('adresse')->nullable();
            $table->string('telephone')->nullable();
            $table->string('pied_extrait')->nullable();
            $table->timestamps();
        });

        Schema::create('paroisses', function (Blueprint $table) {
            $table->id();
            $this->synchro($table);
            $table->string('nom');
            $table->string('localite')->nullable();
            $table->string('cure')->nullable();
            $table->string('telephone')->nullable();
            $table->string('adresse')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique(); // SQLite n'ajoute pas de colonne obligatoire sans valeur par défaut
            $table->unsignedBigInteger('revision')->default(0)->index();
            $table->foreignId('paroisse_id')->nullable()->constrained('paroisses')->nullOnDelete();
        });

        Schema::create('appareils', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nom');
            $table->string('code', 10)->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('derniere_synchro')->nullable();
            $table->timestamps();
        });

        Schema::create('cebs', function (Blueprint $table) {
            $table->id();
            $this->synchro($table);
            $table->foreignId('paroisse_id')->constrained('paroisses');
            $table->string('nom');
            $table->string('quartier')->nullable();
            $table->string('responsable')->nullable();
            $table->string('telephone')->nullable();
            $table->timestamps();
        });

        Schema::create('mouvements', function (Blueprint $table) {
            $table->id();
            $this->synchro($table);
            $table->foreignId('paroisse_id')->nullable()->constrained('paroisses');
            $table->string('nom');
            $table->string('type')->default('mouvement'); // mouvement | service
            $table->string('responsable')->nullable();
            $table->string('telephone')->nullable();
            $table->unsignedInteger('nombre_membres')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('fideles', function (Blueprint $table) {
            $table->id();
            $this->synchro($table);
            $table->foreignId('paroisse_id')->constrained('paroisses');
            $table->foreignId('ceb_id')->nullable()->constrained('cebs');
            $table->string('nom');
            $table->string('prenoms');
            $table->string('sexe', 1);
            $table->date('date_naissance')->nullable();
            $table->string('telephone')->nullable();
            $table->string('profession')->nullable();
            $table->string('famille')->nullable()->index();
            $table->string('statut')->default('actif'); // actif | decede | transfere
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['paroisse_id', 'statut']);
        });

        Schema::create('sacrements', function (Blueprint $table) {
            $table->id();
            $this->synchro($table);
            $table->foreignId('fidele_id')->constrained('fideles');
            $table->foreignId('paroisse_id')->constrained('paroisses'); // celle du fidèle à la date de l'enregistrement
            $table->string('type'); // bapteme | eucharistie | confirmation | mariage | obseques
            $table->date('date_sacrement');
            $table->string('lieu')->nullable();
            $table->string('ministre')->nullable();
            $table->string('parrain')->nullable();
            $table->string('conjoint')->nullable();
            $table->string('numero_registre')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['type', 'date_sacrement']);
        });

        Schema::create('operations', function (Blueprint $table) {
            $table->id();
            $this->synchro($table);
            $table->foreignId('paroisse_id')->constrained('paroisses');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('sens'); // recette | depense
            $table->string('categorie');
            $table->string('libelle');
            $table->unsignedBigInteger('montant');
            $table->date('date_operation');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['paroisse_id', 'date_operation']);
        });

        Schema::create('annonces', function (Blueprint $table) {
            $table->id();
            $this->synchro($table);
            $table->foreignId('paroisse_id')->nullable()->constrained('paroisses');
            $table->string('titre');
            $table->text('contenu');
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->timestamps();
        });

        Schema::create('activites', function (Blueprint $table) {
            $table->id();
            $this->synchro($table);
            $table->foreignId('paroisse_id')->nullable()->constrained('paroisses');
            $table->foreignId('mouvement_id')->nullable()->constrained('mouvements');
            $table->string('titre');
            $table->date('date_activite')->index();
            $table->string('heure', 5)->nullable();
            $table->string('lieu')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['activites', 'annonces', 'operations', 'sacrements', 'fideles', 'mouvements', 'cebs', 'appareils'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paroisse_id');
            $table->dropUnique(['uuid']);
            $table->dropIndex(['revision']);
            $table->dropColumn(['uuid', 'revision']);
        });
        foreach (['paroisses', 'vicariat', 'suppressions', 'sync_compteur'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
