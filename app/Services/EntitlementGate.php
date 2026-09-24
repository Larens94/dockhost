<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Recipe;
use App\Models\Subscription;
use Illuminate\Validation\ValidationException;

class EntitlementGate
{
    /**
     * @param  array{
     *     wants_database?: bool,
     *     wants_storage?: bool,
     *     wants_sftp?: bool,
     *     wants_cache?: bool
     * }  $options
     */
    public function assertCanProvision(Client $client, Recipe $recipe, array $options): Subscription
    {
        if ($client->status !== 'active') {
            throw ValidationException::withMessages([
                'client_id' => 'Client is not active.',
            ]);
        }

        $subscription = $this->activeSubscription($client);

        if (! $subscription?->plan) {
            throw ValidationException::withMessages([
                'client_id' => 'Client has no active subscription.',
            ]);
        }

        $used = $client->sites()->where('usage_held', true)->count();

        if ($used >= $subscription->plan->site_quota) {
            throw ValidationException::withMessages([
                'client_id' => "Site quota reached ({$subscription->plan->site_quota}).",
            ]);
        }

        $entitlements = $subscription->plan->entitlements ?? [];

        if (($options['wants_sftp'] ?? false) && empty($entitlements['sftp'])) {
            throw ValidationException::withMessages([
                'wants_sftp' => 'This plan does not include SFTP.',
            ]);
        }

        if (($options['wants_cache'] ?? false) && empty($entitlements['cache'])) {
            throw ValidationException::withMessages([
                'wants_cache' => 'This plan does not include cache.',
            ]);
        }

        $requires = $recipe->requires ?? [];

        if (in_array('database', $requires, true) && ! ($options['wants_database'] ?? false)) {
            throw ValidationException::withMessages([
                'wants_database' => "{$recipe->name} requires a database.",
            ]);
        }

        if (in_array('storage', $requires, true) && ! ($options['wants_storage'] ?? false) && ! ($options['wants_sftp'] ?? false)) {
            throw ValidationException::withMessages([
                'wants_storage' => "{$recipe->name} requires storage.",
            ]);
        }

        if (in_array('cache', $requires, true) && ! ($options['wants_cache'] ?? false)) {
            throw ValidationException::withMessages([
                'wants_cache' => "{$recipe->name} requires cache.",
            ]);
        }

        return $subscription;
    }

    /**
     * @return array{plan: ?string, quota: int, used: int, can_provision: bool, reason: ?string, entitlements: array<string, bool>}
     */
    public function summary(Client $client): array
    {
        $subscription = $this->activeSubscription($client);
        $used = $client->sites()->where('usage_held', true)->count();
        $quota = (int) ($subscription?->plan?->site_quota ?? 0);
        $reason = null;

        if ($client->status !== 'active') {
            $reason = 'Client is not active.';
        } elseif (! $subscription?->plan) {
            $reason = 'No active subscription.';
        } elseif ($used >= $quota) {
            $reason = "Site quota reached ({$quota}).";
        }

        return [
            'plan' => $subscription?->plan?->name,
            'quota' => $quota,
            'used' => $used,
            'can_provision' => $reason === null,
            'reason' => $reason,
            'entitlements' => [
                'sftp' => (bool) ($subscription?->plan?->entitlements['sftp'] ?? false),
                'cache' => (bool) ($subscription?->plan?->entitlements['cache'] ?? false),
                'dedicated_database' => (bool) ($subscription?->plan?->entitlements['dedicated_database'] ?? false),
            ],
        ];
    }

    private function activeSubscription(Client $client): ?Subscription
    {
        return $client->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->latest('id')
            ->with('plan')
            ->first();
    }
}
