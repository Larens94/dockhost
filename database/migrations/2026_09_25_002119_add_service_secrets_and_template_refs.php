<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->text('service_secrets')->nullable()->after('environment');
        });

        Schema::table('infra_templates', function (Blueprint $table) {
            $table->string('dokploy_ref')->nullable()->after('compose');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('service_secrets');
        });

        Schema::table('infra_templates', function (Blueprint $table) {
            $table->dropColumn('dokploy_ref');
        });
    }
};
