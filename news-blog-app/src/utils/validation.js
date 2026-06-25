import { PROFANITY_WORDS } from './profanity-list';

const PROFANITY_REGEX = new RegExp(
  '\\b(' + PROFANITY_WORDS.join('|') + ')\\b',
  'i'
);

/** Returns true if the given text contains a profane/swear word. */
export function containsProfanity(text) {
  if (!text) return false;
  return PROFANITY_REGEX.test(text);
}

/** Returns true if email looks like a valid email address. */
export function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email || '');
}
