<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infrastructure_environments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('infrastructure_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // production, staging, test, dev, custom…
            $table->string('slug', 64);
            $table->string('dokploy_environment_id')->nullable();
            $table->string('dokploy_compose_id')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['infrastructure_id', 'slug']);
        });

        Schema::table('domains', function (Blueprint $table) {
            $table->foreignId('infrastructure_environment_id')->nullable()->after('infrastructure_id')->constrained()->nullOnDelete();
        });

        // Backfill one default "production" env per infra that already has a Dokploy environment.
        $infrastructures = DB::table('infrastructures')->select([
            'id',
            'dokploy_environment_id',
            'dokploy_compose_id',
        ])->get();

        foreach ($infrastructures as $infrastructure) {
            $envId = DB::table('infrastructure_environments')->insertGetId([
                'infrastructure_id' => $infrastructure->id,
                'name' => 'production',
                'slug' => 'production',
                'dokploy_environment_id' => $infrastructure->dokploy_environment_id,
                'dokploy_compose_id' => $infrastructure->dokploy_compose_id,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('domains')
                ->where('infrastructure_id', $infrastructure->id)
                ->update(['infrastructure_environment_id' => $envId]);
        }
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropConstrainedForeignId('infrastructure_environment_id');
        });

        Schema::dropIfExists('infrastructure_environments');
    }
};
