<?php
/**
 * Server-side profanity check for comments. The client also screens with the
 * matching list in src/utils/profanity-list.js — that check is purely UX
 * (instant feedback), since it runs in the browser and can be bypassed. This
 * is the one that actually decides whether a comment auto-publishes.
 *
 * Keep this list in sync with src/utils/profanity-list.js if either changes.
 */

const PROFANITY_WORDS = [
  'arse', 'ass', 'asshole', 'bastard', 'bitch', 'bollocks', 'bullshit',
  'crap', 'cunt', 'damn', 'dick', 'dickhead', 'douche', 'douchebag',
  'fuck', 'fucked', 'fucker', 'fucking', 'goddamn', 'jackass',
  'motherfucker', 'piss', 'pissed', 'prick', 'pussy', 'shit', 'shite',
  'slut', 'twat', 'wanker', 'whore',
];

function contains_profanity($text) {
  if (!is_string($text) || $text === '') return false;
  $pattern = '/\b(' . implode('|', PROFANITY_WORDS) . ')\b/i';
  return preg_match($pattern, $text) === 1;
}
