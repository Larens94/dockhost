<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infrastructures', function (Blueprint $table) {
            $table->string('dokploy_project_id')->nullable()->after('dokploy_environment_id');
            $table->unsignedSmallInteger('sftp_host_port')->nullable()->after('sftp_host');
        });
    }

    public function down(): void
    {
        Schema::table('infrastructures', function (Blueprint $table) {
            $table->dropColumn(['dokploy_project_id', 'sftp_host_port']);
        });
    }
};
