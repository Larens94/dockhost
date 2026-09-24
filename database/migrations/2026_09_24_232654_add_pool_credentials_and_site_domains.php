<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pools', function (Blueprint $table) {
            $table->text('credentials')->nullable();
        });

        Schema::create('site_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('host')->unique();
            $table->boolean('primary')->default(false);
            $table->string('dokploy_domain_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_domains');

        Schema::table('pools', function (Blueprint $table) {
            $table->dropColumn('credentials');
        });
    }
};
