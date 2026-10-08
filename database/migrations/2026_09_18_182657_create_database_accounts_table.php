<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('engine');
            $table->string('infra_slug');
            $table->string('host');
            $table->unsignedInteger('port');
            $table->string('database_name');
            $table->string('username');
            $table->text('password_encrypted');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_accounts');
    }
};
