<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infrastructures', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('dokploy_environment_id');
            $table->string('dokploy_compose_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('mysql_host');
            $table->unsignedSmallInteger('mysql_port')->default(3306);
            $table->string('mysql_admin_user');
            $table->text('mysql_admin_password');
            $table->string('postgres_host');
            $table->unsignedSmallInteger('postgres_port')->default(5432);
            $table->string('postgres_admin_user');
            $table->text('postgres_admin_password');
            $table->string('postgres_admin_database')->default('postgres');
            $table->string('sftp_host');
            $table->string('storage_root');
            $table->string('sftp_users_file');
            $table->boolean('panel_volumes_attached')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infrastructures');
    }
};
