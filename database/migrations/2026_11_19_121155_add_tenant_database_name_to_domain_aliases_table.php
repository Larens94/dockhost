<?php

// add_tenant_database_name_to_domain_aliases_table — Records MariaDB DB created for multi-tenant alias.
//
// exports: migration
// used_by: app/Models/DomainAlias.php
// rules:   Set when alias create opted into tenant DB; never auto-drop on alias delete.
// agent:   composer-2.5-fast | cursor | 2026-11-19 | s_alias_tenant_db | tenant_database_name column
// message:

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domain_aliases', function (Blueprint $table) {
            $table->string('tenant_database_name', 64)->nullable()->after('dokploy_domain_id');
        });
    }

    public function down(): void
    {
        Schema::table('domain_aliases', function (Blueprint $table) {
            $table->dropColumn('tenant_database_name');
        });
    }
};
