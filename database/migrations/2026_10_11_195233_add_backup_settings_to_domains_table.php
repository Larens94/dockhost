<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->boolean('backup_enabled')->default(true)->after('php_settings');
            $table->string('backup_frequency', 16)->default('daily')->after('backup_enabled'); // daily | weekly
            $table->unsignedSmallInteger('backup_retain_days')->default(14)->after('backup_frequency');
            $table->timestamp('backup_last_ran_at')->nullable()->after('backup_retain_days');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn([
                'backup_enabled',
                'backup_frequency',
                'backup_retain_days',
                'backup_last_ran_at',
            ]);
        });
    }
};
