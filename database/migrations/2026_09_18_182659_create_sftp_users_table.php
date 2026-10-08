<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sftp_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('username');
            $table->text('password_encrypted');
            $table->string('home_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sftp_users');
    }
};
