<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->foreignId('infrastructure_environment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 32); // database | files | full
            $table->string('status', 32)->default('pending'); // pending | running | completed | failed
            $table->string('disk_path')->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['domain_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_backups');
    }
};
