<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infrastructures', function (Blueprint $table) {
            $table->string('pgadmin_domain')->nullable();
            $table->string('minio_domain')->nullable();
            $table->string('pgadmin_email')->nullable();
            $table->text('pgadmin_password')->nullable();
            $table->string('minio_root_user')->nullable();
            $table->text('minio_root_password')->nullable();
            $table->string('redis_host')->nullable();
            $table->string('minio_host')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('infrastructures', function (Blueprint $table) {
            $table->dropColumn([
                'pgadmin_domain',
                'minio_domain',
                'pgadmin_email',
                'pgadmin_password',
                'minio_root_user',
                'minio_root_password',
                'redis_host',
                'minio_host',
            ]);
        });
    }
};
