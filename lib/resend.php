<?php
/**
 * Minimal Resend HTTP API client (https://resend.com/docs/api-reference/emails).
 *
 * Handles all transactional email — the public contact form and admin
 * invite/reset mail. Secrets come from lib/config.php (env-driven).
 *
 * Delivery is best-effort: a failure here must never fatal the request, so
 * resend_send() returns a [bool ok, ?string error] pair and the caller
 * decides how to respond. The contact handler treats a stored-but-unsent
 * submission as a soft success (the lead is already persisted).
 */

require_once __DIR__ . '/config.php';

/**
 * Send one transactional email via Resend.
 *
 * @param string      $to      Recipient email address.
 * @param string      $subject Plain-text subject line.
 * @param string      $html    HTML body.
 * @param string|null $replyTo Optional Reply-To (e.g. the visitor's email).
 * @return array{0:bool,1:?string} [ok, errorMessage]
 */
function resend_send($to, $subject, $html, $replyTo = null) {
  if (RESEND_API_KEY === '') {
    return [false, 'RESEND_API_KEY not configured'];
  }

  $payload = [
    'from'    => RESEND_FROM,
    'to'      => [$to],
    'subject' => $subject,
    'html'    => $html,
  ];
  if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
    $payload['reply_to'] = $replyTo;
  }

  $ch = curl_init('https://api.resend.com/emails');
  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 8,   // fail fast so email delivery never stalls the request
    CURLOPT_HTTPHEADER     => [
      'Authorization: Bearer ' . RESEND_API_KEY,
      'Content-Type: application/json',
    ],
  ]);

  $response = curl_exec($ch);
  $status   = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  $curlErr  = curl_error($ch);
  curl_close($ch);

  if ($response === false) {
    return [false, 'Resend request failed: ' . $curlErr];
  }
  if ($status < 200 || $status >= 300) {
    // Resend returns a JSON {"message": "..."} on error — surface it to the log.
    $body = json_decode($response, true);
    $msg  = is_array($body) && isset($body['message']) ? $body['message'] : $response;
    return [false, 'Resend HTTP ' . $status . ': ' . $msg];
  }
  return [true, null];
}
