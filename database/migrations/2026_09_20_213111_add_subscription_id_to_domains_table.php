<?php

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->foreignId('subscription_id')
                ->nullable()
                ->after('customer_id')
                ->constrained()
                ->nullOnDelete();
        });

        $this->backfillSubscriptionIds();
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_id');
        });
    }

    private function backfillSubscriptionIds(): void
    {
        $domains = DB::table('domains')->whereNull('subscription_id')->get();

        if ($domains->isEmpty()) {
            return;
        }

        $planId = DB::table('service_plans')->where('slug', 'unlimited')->value('id');

        if ($planId === null) {
            $planId = DB::table('service_plans')->insertGetId([
                'name' => 'Unlimited',
                'slug' => 'unlimited',
                'max_domains' => null,
                'disk_mb' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($domains as $domain) {
            $subscriptionId = DB::table('subscriptions')
                ->where('customer_id', $domain->customer_id)
                ->value('id');

            if ($subscriptionId === null) {
                $customer = DB::table('customers')->where('id', $domain->customer_id)->first();
                $baseName = Str::slug((string) ($customer?->name ?? '')).'-spazio';
                $name = $baseName !== '-spazio' ? $baseName : 'spazio-'.$domain->customer_id;

                if (DB::table('subscriptions')->where('name', $name)->exists()) {
                    $name = $name.'-'.$domain->customer_id;
                }

                $subscriptionId = DB::table('subscriptions')->insertGetId([
                    'customer_id' => $domain->customer_id,
                    'service_plan_id' => $planId,
                    'name' => $name,
                    'status' => SubscriptionStatus::Active->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('domains')->where('id', $domain->id)->update([
                'subscription_id' => $subscriptionId,
            ]);
        }
    }
};
