<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('database_accounts', function (Blueprint $table) {
            $table->foreignId('infrastructure_id')->nullable()->after('domain_id')->constrained()->nullOnDelete();
            $table->string('privilege')->default('all')->after('username');
        });

        Schema::table('sftp_users', function (Blueprint $table) {
            $table->foreignId('infrastructure_id')->nullable()->after('domain_id')->constrained()->nullOnDelete();
        });

        $domains = DB::table('domains')->get(['id', 'infrastructure_id']);

        foreach ($domains as $domain) {
            if ($domain->infrastructure_id === null) {
                continue;
            }

            DB::table('database_accounts')
                ->where('domain_id', $domain->id)
                ->whereNull('infrastructure_id')
                ->update(['infrastructure_id' => $domain->infrastructure_id]);

            DB::table('sftp_users')
                ->where('domain_id', $domain->id)
                ->whereNull('infrastructure_id')
                ->update(['infrastructure_id' => $domain->infrastructure_id]);
        }
    }

    public function down(): void
    {
        Schema::table('database_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('infrastructure_id');
            $table->dropColumn('privilege');
        });

        Schema::table('sftp_users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('infrastructure_id');
        });
    }
};
