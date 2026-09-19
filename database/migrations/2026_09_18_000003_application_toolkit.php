<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->json('toolkit')->nullable()->after('steps');
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->string('repository')->nullable()->after('domain');
            $table->json('toolkit_state')->nullable()->after('options');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['repository', 'toolkit_state']);
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('toolkit');
        });
    }
};
