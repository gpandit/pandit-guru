<?php
/**
 * Bootstrap config for the contact form:
 *   - country: visitor's ISO-2 code so intl-tel-input preselects the flag/dial code.
 *   - recaptchaSiteKey: the public reCAPTCHA v3 site key (empty = captcha disabled).
 *
 * Best-effort only — an unknown country returns "" and the client falls back
 * to a default. Loads .env directly (not the full config) to stay lightweight
 * and avoid the DB/key validation on this public GET.
 */

require __DIR__ . '/lib/env.php';
load_env_file(__DIR__ . '/lib/.env');
require __DIR__ . '/lib/geo.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$code = geo_country_code($ip) ?? '';
$siteKey = getenv('RECAPTCHA_SITE_KEY');
$siteKey = ($siteKey === false) ? '' : $siteKey;

echo json_encode(['country' => $code, 'recaptchaSiteKey' => $siteKey]);
