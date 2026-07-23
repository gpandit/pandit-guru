<?php
/**
 * Tiny helper for the contact form's phone input: returns the visitor's ISO-2
 * country code so intl-tel-input can preselect the right flag/dial code.
 *
 * Best-effort only — a null/unknown result is returned as {"country":""}, and
 * the client falls back to a default country. Never blocks the form.
 */

require __DIR__ . '/lib/geo.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$code = geo_country_code($ip) ?? '';

echo json_encode(['country' => $code]);
