<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->string('backup_schedule', 32)->default('0 3 * * *')->after('backup_enabled');
        });

        DB::table('domains')->orderBy('id')->chunkById(100, function ($domains): void {
            foreach ($domains as $domain) {
                $cron = ($domain->backup_frequency ?? 'daily') === 'weekly'
                    ? '0 3 * * 0'
                    : '0 3 * * *';

                DB::table('domains')->where('id', $domain->id)->update([
                    'backup_schedule' => $cron,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn('backup_schedule');
        });
    }
};
