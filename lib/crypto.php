<?php
/**
 * Application-level encryption for PII at rest.
 *
 * - Sensitive fields (name, phone, message, email, resume bytes) are
 *   encrypted with libsodium's authenticated secretbox using ENCRYPTION_KEY.
 * - Email also gets a deterministic HMAC "blind index" (BLIND_INDEX_KEY) so
 *   we can dedupe / look up by email without storing it in plaintext.
 *
 * Keys live in lib/config.php and must be kept out of version control on the
 * live server. Generate them once with:
 *   php -r "echo base64_encode(sodium_crypto_secretbox_keygen()), PHP_EOL;"   // ENCRYPTION_KEY
 *   php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"                    // BLIND_INDEX_KEY
 */

require_once __DIR__ . '/config.php';

if (!extension_loaded('sodium')) {
  error_log('crypto: libsodium extension not available — PII cannot be encrypted.');
}

function _enc_key() {
  static $k = null;
  if ($k === null) {
    $k = base64_decode(ENCRYPTION_KEY, true);
    if ($k === false || strlen($k) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
      throw new RuntimeException('Invalid ENCRYPTION_KEY (expected base64 of 32 bytes).');
    }
  }
  return $k;
}

/** Encrypt a string; returns base64(nonce || ciphertext), or '' for empty input. */
function encrypt_value($plaintext) {
  if ($plaintext === null || $plaintext === '') return '';
  $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
  $cipher = sodium_crypto_secretbox((string) $plaintext, $nonce, _enc_key());
  return base64_encode($nonce . $cipher);
}

/** Decrypt a value produced by encrypt_value(); returns '' on empty/failure. */
function decrypt_value($encoded) {
  if ($encoded === null || $encoded === '') return '';
  $raw = base64_decode($encoded, true);
  if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
    return '';
  }
  $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
  $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
  $plain = sodium_crypto_secretbox_open($cipher, $nonce, _enc_key());
  return $plain === false ? '' : $plain;
}

/** Encrypt raw binary (e.g. a resume PDF). Same scheme as encrypt_value. */
function encrypt_bytes($bytes) {
  $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
  return $nonce . sodium_crypto_secretbox($bytes, $nonce, _enc_key());
}

function decrypt_bytes($raw) {
  if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
    return false;
  }
  $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
  $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
  return sodium_crypto_secretbox_open($cipher, $nonce, _enc_key());
}

/** Deterministic, searchable HMAC of an email (case/space-normalised). */
function blind_index($email) {
  $norm = strtolower(trim((string) $email));
  if ($norm === '') return '';
  $key = base64_decode(BLIND_INDEX_KEY, true);
  if ($key === false) throw new RuntimeException('Invalid BLIND_INDEX_KEY.');
  return hash_hmac('sha256', $norm, $key); // hex, 64 chars
}
