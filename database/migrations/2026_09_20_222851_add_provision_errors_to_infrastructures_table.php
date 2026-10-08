<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infrastructures', function (Blueprint $table) {
            $table->text('last_error')->nullable()->after('panel_volumes_attached');
            $table->text('panel_volumes_error')->nullable()->after('last_error');
        });
    }

    public function down(): void
    {
        Schema::table('infrastructures', function (Blueprint $table) {
            $table->dropColumn(['last_error', 'panel_volumes_error']);
        });
    }
};
