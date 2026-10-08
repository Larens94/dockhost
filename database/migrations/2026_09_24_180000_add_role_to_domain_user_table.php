<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domain_user', function (Blueprint $table) {
            $table->string('role', 32)->default('developer')->after('domain_id');
        });

        // Existing grants before owner column: treat as developer; admins should promote an owner per domain.
    }

    public function down(): void
    {
        Schema::table('domain_user', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
