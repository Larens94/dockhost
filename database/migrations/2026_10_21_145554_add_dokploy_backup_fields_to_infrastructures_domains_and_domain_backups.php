<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infrastructures', function (Blueprint $table) {
            $table->string('dokploy_backup_destination_id')->nullable()->after('dokploy_compose_id');
        });

        Schema::table('domains', function (Blueprint $table) {
            $table->string('dokploy_backup_id')->nullable()->after('backup_last_ran_at');
        });

        Schema::table('domain_backups', function (Blueprint $table) {
            $table->string('storage_provider', 16)->default('local')->after('kind');
            $table->string('dokploy_backup_id')->nullable()->after('storage_provider');
        });
    }

    public function down(): void
    {
        Schema::table('domain_backups', function (Blueprint $table) {
            $table->dropColumn(['storage_provider', 'dokploy_backup_id']);
        });

        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn('dokploy_backup_id');
        });

        Schema::table('infrastructures', function (Blueprint $table) {
            $table->dropColumn('dokploy_backup_destination_id');
        });
    }
};
