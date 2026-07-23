<?php
/**
 * reCAPTCHA v3 verification (https://developers.google.com/recaptcha/docs/v3).
 *
 * Optional spam protection for the public contact form. When RECAPTCHA_SECRET
 * is unset the feature is disabled and recaptcha_verify() passes through, so
 * the form keeps working with no keys configured.
 */

require_once __DIR__ . '/config.php';

/**
 * Verify a reCAPTCHA v3 token against Google's siteverify endpoint.
 *
 * @param string      $token          The g-recaptcha-response token from the client.
 * @param string|null $ip             Visitor IP (optional, improves scoring).
 * @param string      $expectedAction The action name set client-side.
 * @return array{0:bool,1:?float,2:?string} [ok, score, errorMessage]
 *   When disabled (no secret) → [true, null, null].
 */
function recaptcha_verify($token, $ip = null, $expectedAction = 'contact') {
  if (RECAPTCHA_SECRET === '') {
    return [true, null, null]; // feature disabled
  }
  $token = trim((string) $token);
  if ($token === '') {
    return [false, null, 'missing token'];
  }

  $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
      'secret'   => RECAPTCHA_SECRET,
      'response' => $token,
      'remoteip' => $ip ?? '',
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 8,
  ]);
  $resp = curl_exec($ch);
  $err  = curl_error($ch);
  curl_close($ch);

  if ($resp === false) {
    return [false, null, 'siteverify request failed: ' . $err];
  }
  $data = json_decode($resp, true);
  if (!is_array($data)) {
    return [false, null, 'invalid siteverify response'];
  }
  if (empty($data['success'])) {
    return [false, null, 'reCAPTCHA rejected: ' . implode(',', $data['error-codes'] ?? ['unknown'])];
  }
  $score = isset($data['score']) ? (float) $data['score'] : null;
  if ($expectedAction !== '' && isset($data['action']) && $data['action'] !== $expectedAction) {
    return [false, $score, 'action mismatch'];
  }
  return [true, $score, null];
}
