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
            $table->string('stack', 32)->default('none')->after('infra_slug');
        });

        DB::table('domains')
            ->whereIn('id', DB::table('dokploy_applications')->select('domain_id'))
            ->update(['stack' => 'laravel']);
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn('stack');
        });
    }
};
