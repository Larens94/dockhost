<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('status')->default('active');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ip')->nullable();
            $table->string('role')->default('worker');
            $table->string('status')->default('online');
            $table->string('dokploy_server_id')->nullable();
            $table->timestamps();
        });

        Schema::create('pools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kind'); // database, storage, runtime, cache, sftp...
            $table->string('engine')->nullable(); // mariadb, postgres, redis...
            $table->foreignId('server_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('capacity')->default(100);
            $table->unsignedInteger('usage')->default(0);
            $table->string('dokploy_ref')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('service_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kind');
            $table->string('image');
            $table->string('mode')->default('shared'); // shared|dedicated
            $table->string('support')->default('official'); // official|beta|community
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('infra_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('summary')->nullable();
            $table->string('version')->default('1.0.0');
            $table->json('services')->nullable();
            $table->longText('compose')->nullable();
            $table->timestamps();
        });

        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('stack'); // laravel, wordpress, node, static, custom...
            $table->string('summary')->nullable();
            $table->string('version')->default('1.0.0');
            $table->string('status')->default('stable'); // stable|beta|draft
            $table->json('requires')->nullable(); // pool kinds required
            $table->json('steps')->nullable(); // provisioning steps (data, not PHP)
            $table->timestamps();
        });

        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->restrictOnDelete();
            $table->string('domain');
            $table->string('status')->default('pending');
            $table->string('dokploy_app_id')->nullable();
            $table->json('pool_ids')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('infra_templates');
        Schema::dropIfExists('service_catalog');
        Schema::dropIfExists('pools');
        Schema::dropIfExists('servers');
        Schema::dropIfExists('clients');
    }
};
