<?php

// create_domain_aliases_table — Alias and subdomain hostnames for a primary Domain.
//
// exports: migration
// used_by: app/Models/DomainAlias.php
// rules:   fqdn unique globally vs domains.fqdn. Dokploy domain.create on same application when web_service.
// agent:   composer-2.5-fast | cursor | 2026-10-12 | s_domain_alias | Plesk-like alias/subdomain hosts
// message:

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('fqdn')->unique();
            $table->string('kind', 32)->default('alias'); // alias | subdomain
            $table->boolean('web_service')->default(true);
            $table->boolean('redirect_301')->default(false);
            $table->boolean('mail_service')->default(false);
            $table->boolean('sync_dns')->default(false);
            $table->string('dokploy_domain_id')->nullable();
            $table->timestamps();

            $table->index(['domain_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_aliases');
    }
};
