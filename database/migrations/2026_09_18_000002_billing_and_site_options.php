<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('company')->nullable()->after('name');
            $table->string('stripe_customer_id')->nullable()->after('status');
            $table->string('billing_email')->nullable()->after('stripe_customer_id');
            $table->string('billing_status')->default('none')->after('billing_email');
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('stripe_price_id')->nullable();
            $table->unsignedInteger('amount_cents')->default(0);
            $table->string('currency', 3)->default('eur');
            $table->string('interval')->default('month'); // month|year
            $table->unsignedInteger('site_quota')->default(1);
            $table->json('features')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('status')->default('incomplete'); // active|trialing|past_due|canceled|incomplete
            $table->timestamp('current_period_end')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->json('options')->nullable()->after('meta');
            // options: wants_database, database_pool_id, wants_storage, storage_pool_id,
            // wants_sftp, wants_cache, cache_pool_id, recipe_id already on site
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->boolean('enabled')->default(true)->after('status');
            $table->unsignedInteger('sort')->default(100)->after('enabled');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn(['enabled', 'sort']);
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('options');
        });

        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'company',
                'stripe_customer_id',
                'billing_email',
                'billing_status',
            ]);
        });
    }
};
