<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infrastructures', function (Blueprint $table) {
            $table->string('phpmyadmin_domain')->nullable();
            $table->text('mysql_root_password')->nullable();
            $table->text('sftp_bootstrap_password')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('infrastructures', function (Blueprint $table) {
            $table->dropColumn(['phpmyadmin_domain', 'mysql_root_password', 'sftp_bootstrap_password']);
        });
    }
};
