<?php
/**
 * Best-effort IP → country resolution.
 *
 * Uses ip-api.com (free, no key, ~45 req/min) with a short timeout. Every call
 * site MUST treat a null return as "unknown" — geo lookup is a nice-to-have and
 * must never block or fail a form submission. For higher volume / no external
 * dependency, swap this for a local MaxMind GeoLite2 .mmdb lookup (see the
 * lead-magnet roadmap, "Car Park").
 */

/** Returns a country name (e.g. "United Kingdom") or null if it can't be determined. */
function geo_country($ip) {
  $ip = trim((string) $ip);
  if ($ip === '') return null;
  // Skip private / reserved ranges — they'd just return "unknown".
  if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
    return null;
  }

  $url = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,country';
  $ctx = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);
  $raw = @file_get_contents($url, false, $ctx);
  if ($raw === false) return null;

  $data = json_decode($raw, true);
  if (!is_array($data) || ($data['status'] ?? '') !== 'success') return null;
  $country = trim((string) ($data['country'] ?? ''));
  return $country === '' ? null : $country;
}
