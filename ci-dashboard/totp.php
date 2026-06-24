<?php
// Minimal RFC 6238 TOTP (no dependencies), 30s step, 6 digits, SHA1.

function totp_base32_decode(string $b32): string
{
    $b32 = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32));
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($b32) as $char) {
        $pos = strpos($alphabet, $char);
        if ($pos === false) {
            continue;
        }
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $bytes .= chr(bindec($byte));
        }
    }
    return $bytes;
}

function totp_base32_encode(string $data): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($data) as $byte) {
        $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
    }
    $b32 = '';
    foreach (str_split($bits, 5) as $chunk) {
        if (strlen($chunk) < 5) {
            $chunk = str_pad($chunk, 5, '0');
        }
        $b32 .= $alphabet[bindec($chunk)];
    }
    return $b32;
}

function totp_generate_secret(int $bytes = 20): string
{
    return totp_base32_encode(random_bytes($bytes));
}

function totp_code(string $secretBase32, ?int $timestamp = null, int $step = 30, int $digits = 6): string
{
    $timestamp = $timestamp ?? time();
    $key = totp_base32_decode($secretBase32);
    $counter = intdiv($timestamp, $step);
    $binCounter = pack('N*', 0, $counter); // 64-bit big-endian counter
    $hash = hash_hmac('sha1', $binCounter, $key, true);
    $offset = ord($hash[19]) & 0xf;
    $truncated = (
        ((ord($hash[$offset]) & 0x7f) << 24) |
        ((ord($hash[$offset + 1]) & 0xff) << 16) |
        ((ord($hash[$offset + 2]) & 0xff) << 8) |
        (ord($hash[$offset + 3]) & 0xff)
    );
    $code = $truncated % (10 ** $digits);
    return str_pad((string)$code, $digits, '0', STR_PAD_LEFT);
}

// Accepts a code if it matches the current step or one step before/after,
// to tolerate clock drift between the server and the user's phone.
function totp_verify(string $secretBase32, string $code, int $window = 1, int $step = 30): bool
{
    $code = preg_replace('/\s+/', '', $code);
    $now = time();
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totp_code($secretBase32, $now + ($i * $step), $step), $code)) {
            return true;
        }
    }
    return false;
}

// Returns the otpauth:// URI for manual entry into an authenticator app.
// Deliberately not rendered as a QR code via a third-party service, since
// that would leak the TOTP secret off-server.
function totp_otpauth_uri(string $secretBase32, string $label, string $issuer): string
{
    return sprintf(
        'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
        rawurlencode($issuer . ':' . $label),
        $secretBase32,
        rawurlencode($issuer)
    );
}
