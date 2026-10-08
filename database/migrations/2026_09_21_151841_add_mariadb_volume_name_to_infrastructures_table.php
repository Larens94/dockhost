<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infrastructures', function (Blueprint $table) {
            $table->unsignedInteger('mariadb_volume_generation')->default(0);
            $table->string('mariadb_volume_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('infrastructures', function (Blueprint $table) {
            $table->dropColumn(['mariadb_volume_generation', 'mariadb_volume_name']);
        });
    }
};
