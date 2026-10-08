<?php

// TwoFactorRecoveryCodes.php — Generate and verify one-time recovery codes (hashed at rest).
//
// exports: TwoFactorRecoveryCodes | TwoFactorRecoveryCodes::generatePlain(int $count = 8): list<string> | TwoFactorRecoveryCodes::hashPlainCodes(array $plainCodes): list<string> | TwoFactorRecoveryCodes::consume(string $plainCode, array $hashedCodes): ?array
// used_by: app/Http/Controllers/Account/TwoFactorAuthenticationController.php
//         app/Http/Controllers/Auth/TwoFactorChallengeController.php
// rules:   Plain codes shown once at enable — store only bcrypt hashes in users.two_factor_recovery_codes.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_panel_2fa | Hashed recovery codes.

namespace App\Support;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class TwoFactorRecoveryCodes
{
    /**
     * @return list<string>
     */
    public static function generatePlain(int $count = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(4).'-'.Str::random(4));
        }

        return $codes;
    }

    /**
     * @param  list<string>  $plainCodes
     * @return list<string>
     */
    public static function hashPlainCodes(array $plainCodes): array
    {
        return array_map(static fn (string $code): string => Hash::make(strtoupper(str_replace(' ', '', $code))), $plainCodes);
    }

    /**
     * @param  list<string>  $hashedCodes
     * @return array{remaining: list<string>}|null
     */
    public static function consume(string $plainCode, array $hashedCodes): ?array
    {
        $normalized = strtoupper(str_replace(' ', '', $plainCode));

        foreach ($hashedCodes as $index => $hash) {
            if (Hash::check($normalized, $hash)) {
                $remaining = $hashedCodes;
                unset($remaining[$index]);

                return ['remaining' => array_values($remaining)];
            }
        }

        return null;
    }
}
