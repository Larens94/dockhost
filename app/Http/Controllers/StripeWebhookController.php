<?php

namespace App\Http\Controllers;

use App\Services\StripeBillingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeBillingService $billing): Response
    {
        $secret = (string) config('dockhost.stripe.webhook_secret');
        $payload = $request->getContent();
        $event = $this->event($payload, $request->header('Stripe-Signature'), $secret);

        if ($event === null) {
            return response('Invalid payload', 400);
        }

        $billing->applyWebhook($event);

        return response('ok');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function event(string $payload, ?string $header, string $secret): ?array
    {
        if ($secret === '' || $header === null || $header === '') {
            return null;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($key === 't') {
                $timestamp = $value;
            }

            if ($key === 'v1' && is_string($value)) {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || abs(time() - (int) $timestamp) > 300) {
            return null;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        $valid = false;

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                $valid = true;
            }
        }

        if (! $valid) {
            return null;
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : null;
    }
}
