<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Catéchèse : catéchistes, groupes (niveau + année pastorale), inscriptions des catéchumènes, séances. */
return new class extends Migration
{
    private function synchro(Blueprint $table): void
    {
        $table->uuid('uuid')->unique();
        $table->unsignedBigInteger('revision')->default(0)->index();
    }

    public function up(): void
    {
        Schema::create('catechistes', function (Blueprint $table) {
            $table->id();
            $this->synchro($table);
            $table->foreignId('secteur_id')->constrained('secteurs');
            $table->foreignId('fidele_id')->nullable()->constrained('fideles');
            $table->string('nom');
            $table->string('prenoms');
            $table->string('telephone')->nullable();
            $table->string('formation')->default('aucune'); // aucune | initiale | continue | diplome
            $table->date('date_debut')->nullable();
            $table->string('statut')->default('actif'); // actif | inactif
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('groupes_catechese', function (Blueprint $table) {
            $table->id();
            $this->synchro($table);
            $table->foreignId('secteur_id')->constrained('secteurs');
            $table->foreignId('catechiste_id')->nullable()->constrained('catechistes');
            $table->string('annee', 9); // année pastorale : 2026-2027
            $table->string('nom');
            $table->string('niveau');
            $table->string('jour_rencontre')->nullable();
            $table->string('heure', 5)->nullable();
            $table->string('lieu')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['secteur_id', 'annee']);
        });

        Schema::create('inscriptions', function (Blueprint $table) {
            $table->id();
            $this->synchro($table);
            $table->foreignId('secteur_id')->constrained('secteurs'); // celui du fidèle
            $table->foreignId('fidele_id')->constrained('fideles');
            $table->foreignId('groupe_id')->constrained('groupes_catechese');
            $table->date('date_inscription');
            $table->string('statut')->default('inscrit'); // inscrit | admis | ajourne | abandon
            $table->text('observation')->nullable();
            $table->timestamps();
            $table->unique(['fidele_id', 'groupe_id']);
        });

        Schema::create('seances', function (Blueprint $table) {
            $table->id();
            $this->synchro($table);
            $table->foreignId('secteur_id')->constrained('secteurs'); // celui du groupe
            $table->foreignId('groupe_id')->constrained('groupes_catechese');
            $table->foreignId('catechiste_id')->nullable()->constrained('catechistes');
            $table->date('date_seance');
            $table->string('theme');
            $table->unsignedInteger('presents')->default(0);
            $table->unsignedInteger('absents')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['groupe_id', 'date_seance']);
        });
    }

    public function down(): void
    {
        foreach (['seances', 'inscriptions', 'groupes_catechese', 'catechistes'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
