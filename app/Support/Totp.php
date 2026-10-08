<?php

// Totp.php — RFC 6238 TOTP (SHA1) without external packages.
//
// exports: Totp | Totp::generateSecret(): string | Totp::verify(string $secret, string $code, int $window = 1): bool | Totp::provisioningUri(string $secret, string $accountName, string $issuer): string
// used_by: app/Http/Controllers/Account/TwoFactorAuthenticationController.php
//         app/Http/Controllers/Auth/TwoFactorChallengeController.php
// rules:   Base32 secret only in setup flow — never log codes or secrets.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_panel_2fa | Minimal TOTP for optional panel 2FA.

namespace App\Support;

final class Totp
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $length = 20): string
    {
        $bytes = random_bytes($length);

        return self::base32Encode($bytes);
    }

    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $timeSlice = (int) floor(time() / 30);

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals(self::codeForSlice($secret, $timeSlice + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    public static function oneTimeCode(string $secret, ?int $timestamp = null): string
    {
        $timeSlice = (int) floor(($timestamp ?? time()) / 30);

        return self::codeForSlice($secret, $timeSlice);
    }

    public static function provisioningUri(string $secret, string $accountName, string $issuer): string
    {
        $label = rawurlencode($issuer.':'.$accountName);
        $issuerParam = rawurlencode($issuer);
        $secretParam = rawurlencode($secret);

        return "otpauth://totp/{$label}?secret={$secretParam}&issuer={$issuerParam}&algorithm=SHA1&digits=6&period=30";
    }

    private static function codeForSlice(string $secret, int $timeSlice): string
    {
        $secretKey = self::base32Decode($secret);
        $time = pack('N*', 0, $timeSlice);
        $hash = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        );
        $otp = $binary % 1_000_000;

        return str_pad((string) $otp, 6, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $encoded .= self::BASE32_ALPHABET[bindec($chunk)];
        }

        return $encoded;
    }

    private static function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        $bits = '';

        foreach (str_split($secret) as $char) {
            $index = strpos(self::BASE32_ALPHABET, $char);
            if ($index === false) {
                continue;
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) < 8) {
                break;
            }
            $bytes .= chr(bindec($chunk));
        }

        return $bytes;
    }
}
