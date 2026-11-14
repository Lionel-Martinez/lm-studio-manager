<?php

namespace App\Support;

/**
 * Contraseñas de un solo uso basadas en el tiempo (TOTP, RFC 6238) con HMAC-SHA1,
 * 6 dígitos y pasos de 30 s: compatible con Google Authenticator, Authy, 1Password, etc.
 */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public const PERIOD = 30;

    public const DIGITS = 6;

    /** Secreto nuevo de 160 bits, en Base32 (el formato que piden las apps). */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    /** Código de 6 dígitos para un instante (por defecto, ahora). */
    public static function code(string $secret, ?int $timestamp = null): string
    {
        return self::codeForStep($secret, self::step($timestamp));
    }

    public static function step(?int $timestamp = null): int
    {
        return intdiv($timestamp ?? time(), self::PERIOD);
    }

    /**
     * Comprueba un código admitiendo ±`$window` pasos por diferencias de reloj.
     * Devuelve el paso que coincidió o null. Con `$afterStep` rechaza códigos ya usados.
     */
    public static function verify(string $secret, string $code, int $window = 1, ?int $afterStep = null, ?int $timestamp = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/^\d{'.self::DIGITS.'}$/', $code)) {
            return null;
        }

        $current = self::step($timestamp);
        $matched = null;

        // Se recorren todos los pasos (sin cortar al primero) para no filtrar información por tiempo.
        for ($step = $current - $window; $step <= $current + $window; $step++) {
            if (hash_equals(self::codeForStep($secret, $step), $code) && ($afterStep === null || $step > $afterStep)) {
                $matched = $step;
            }
        }

        return $matched;
    }

    /** Dirección otpauth:// que leen las apps (se muestra como QR o como clave manual). */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer.':'.$account),
            $secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD,
        );
    }

    private static function codeForStep(string $secret, int $step): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($binary % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function base32Decode(string $secret): string
    {
        $bits = '';
        foreach (str_split(strtoupper(rtrim($secret, '='))) as $char) {
            $value = strpos(self::ALPHABET, $char);
            if ($value === false) {
                continue;
            }
            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $bytes .= chr(bindec($byte));
            }
        }

        return $bytes;
    }
}
