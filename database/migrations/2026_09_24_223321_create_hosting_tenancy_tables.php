<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('superadmin');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->json('entitlements')->nullable();
        });

        Schema::table('pools', function (Blueprint $table) {
            $table->string('runtime_version')->nullable();
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->text('environment')->nullable();
            $table->text('last_error')->nullable();
            $table->boolean('usage_held')->default(false);
        });

        Schema::create('site_pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pool_id')->constrained()->cascadeOnDelete();
            $table->string('purpose');
            $table->timestamps();

            $table->unique(['site_id', 'pool_id', 'purpose']);
        });

        Schema::create('site_databases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pool_id')->nullable()->constrained()->nullOnDelete();
            $table->string('engine');
            $table->string('schema_name');
            $table->string('username');
            $table->text('password');
            $table->string('host')->nullable();
            $table->unsignedSmallInteger('port')->nullable();
            $table->string('status')->default('reserved');
            $table->string('dokploy_ref')->nullable();
            $table->timestamps();
        });

        Schema::create('site_sftp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pool_id')->nullable()->constrained()->nullOnDelete();
            $table->string('username');
            $table->text('password');
            $table->string('chroot_path');
            $table->string('status')->default('reserved');
            $table->timestamps();
        });

        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        $sites = DB::table('sites')->get();

        foreach ($sites as $site) {
            $poolIds = json_decode($site->pool_ids ?? '[]', true) ?: [];

            foreach ($poolIds as $poolId) {
                $pool = DB::table('pools')->where('id', $poolId)->first();

                if (! $pool) {
                    continue;
                }

                DB::table('site_pools')->insert([
                    'site_id' => $site->id,
                    'pool_id' => $poolId,
                    'purpose' => $pool->kind,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($poolIds !== []) {
                DB::table('sites')->where('id', $site->id)->update(['usage_held' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audits');
        Schema::dropIfExists('site_sftp_accounts');
        Schema::dropIfExists('site_databases');
        Schema::dropIfExists('site_pools');

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['environment', 'last_error', 'usage_held']);
        });

        Schema::table('pools', function (Blueprint $table) {
            $table->dropColumn('runtime_version');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('entitlements');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
