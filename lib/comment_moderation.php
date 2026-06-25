<?php
/**
 * Decide whether a submitted comment auto-publishes or is held for review.
 * Per the product decision: clean comments post instantly; anything flagged
 * goes to a moderation queue rather than being silently rejected — a human
 * always gets the final say on anything borderline.
 *
 * Returns ['status' => 'approved'|'pending'|'spam', 'reason' => string|null].
 * 'reason' is shown only in the admin moderation queue, never to the reader.
 */

require_once __DIR__ . '/profanity.php';

const COMMENT_MAX_LINKS = 1;       // >1 URL in a comment reads as promotional
const COMMENT_RELEVANCE_MIN_WORDS = 15; // below this, skip the relevance check entirely

function moderate_comment($body, $post) {
  if (contains_profanity($body)) {
    return ['status' => 'pending', 'reason' => 'profanity detected'];
  }

  $linkCount = preg_match_all('/https?:\/\/|www\./i', $body);
  if ($linkCount > COMMENT_MAX_LINKS) {
    return ['status' => 'spam', 'reason' => "contains $linkCount links"];
  }

  // Excessive caps is a common low-effort-spam / shouting signal.
  $letters = preg_replace('/[^a-zA-Z]/', '', $body);
  if (strlen($letters) > 20) {
    $upper = preg_replace('/[^A-Z]/', '', $body);
    if (strlen($upper) / strlen($letters) > 0.7) {
      return ['status' => 'pending', 'reason' => 'excessive capitalisation'];
    }
  }

  // Relevance: only checked for longer comments, since short genuine replies
  // ("Great read!", "Thanks for this.") legitimately share no vocabulary with
  // the post and would otherwise be flagged unfairly.
  $wordCount = str_word_count($body);
  if ($wordCount >= COMMENT_RELEVANCE_MIN_WORDS) {
    $topic = strtolower(($post['title'] ?? '') . ' ' . implode(' ', json_decode($post['tags'] ?? '[]', true) ?: []));
    $topicWords = array_unique(array_filter(preg_split('/[^a-z0-9]+/', $topic), fn($w) => strlen($w) > 3));
    $commentWords = array_filter(preg_split('/[^a-z0-9]+/', strtolower($body)), fn($w) => strlen($w) > 3);
    $overlap = array_intersect($topicWords, $commentWords);
    if (count($topicWords) > 0 && count($overlap) === 0) {
      return ['status' => 'pending', 'reason' => 'no topical overlap with post (possible off-topic/spam)'];
    }
  }

  return ['status' => 'approved', 'reason' => null];
}
