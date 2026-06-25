<?php
/**
 * RFC 6238 TOTP (time-based one-time passwords) — pure PHP, no dependencies.
 * Compatible with Google Authenticator, Authy, 1Password, etc.
 * 6 digits, 30-second step, SHA1 (the authenticator-app default).
 */

/** Generate a new random base32 secret (default 160 bits). */
function totp_generate_secret($length = 32) {
  $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  $bytes = random_bytes((int) ceil($length * 5 / 8));
  $secret = '';
  $bits = 0; $value = 0;
  for ($i = 0; $i < strlen($bytes); $i++) {
    $value = ($value << 8) | ord($bytes[$i]);
    $bits += 8;
    while ($bits >= 5) {
      $secret .= $alphabet[($value >> ($bits - 5)) & 31];
      $bits -= 5;
    }
  }
  return substr($secret, 0, $length);
}

function _base32_decode($b32) {
  $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
  $bits = 0; $value = 0; $out = '';
  for ($i = 0; $i < strlen($b32); $i++) {
    $idx = strpos($alphabet, $b32[$i]);
    if ($idx === false) continue;
    $value = ($value << 5) | $idx;
    $bits += 5;
    if ($bits >= 8) {
      $out .= chr(($value >> ($bits - 8)) & 0xFF);
      $bits -= 8;
    }
  }
  return $out;
}

/** Compute the TOTP code for a given secret + time counter. */
function _totp_at($secret, $counter, $digits = 6) {
  $key = _base32_decode($secret);
  $binCounter = pack('N*', 0) . pack('N*', $counter); // 64-bit big-endian
  $hash = hash_hmac('sha1', $binCounter, $key, true);
  $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
  $part = (
      ((ord($hash[$offset]) & 0x7F) << 24) |
      ((ord($hash[$offset + 1]) & 0xFF) << 16) |
      ((ord($hash[$offset + 2]) & 0xFF) << 8) |
      (ord($hash[$offset + 3]) & 0xFF)
  );
  return str_pad((string) ($part % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
}

/**
 * Verify a user-supplied code against the secret, allowing a ±1 step window
 * for clock drift. Constant-time comparison.
 */
function totp_verify($secret, $code, $window = 1) {
  $code = preg_replace('/\D/', '', (string) $code);
  if (strlen($code) !== 6) return false;
  $counter = (int) floor(time() / 30);
  for ($i = -$window; $i <= $window; $i++) {
    if (hash_equals(_totp_at($secret, $counter + $i), $code)) {
      return true;
    }
  }
  return false;
}

/** Build the otpauth:// URI used to generate the enrolment QR code. */
function totp_provisioning_uri($secret, $account, $issuer) {
  $label = rawurlencode($issuer . ':' . $account);
  $params = http_build_query([
    'secret' => $secret,
    'issuer' => $issuer,
    'algorithm' => 'SHA1',
    'digits' => 6,
    'period' => 30,
  ]);
  return "otpauth://totp/$label?$params";
}
