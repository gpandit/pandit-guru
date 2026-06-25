<?php
/**
 * MySQL/MariaDB data layer (PDO). The schema is created automatically on the
 * first connection, so deployment only requires an empty database + creds in
 * lib/config.php. Sensitive columns hold ciphertext (see lib/crypto.php).
 */

require_once __DIR__ . '/config.php';

function db() {
  static $pdo = null;
  if ($pdo !== null) return $pdo;

  $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  db_migrate($pdo);
  return $pdo;
}

function db_migrate(PDO $pdo) {
  // Encrypted PII columns are stored as TEXT (base64 of nonce+ciphertext).
  $pdo->exec("CREATE TABLE IF NOT EXISTS leads (
    id CHAR(16) PRIMARY KEY,
    source VARCHAR(20) NOT NULL,
    name_enc TEXT,
    email_bi CHAR(64),
    email_enc TEXT,
    company_enc TEXT,
    phone_enc TEXT,
    service VARCHAR(255),
    message_enc TEXT,
    ip VARCHAR(64),
    created_at DATETIME NOT NULL,
    INDEX idx_leads_email_bi (email_bi),
    INDEX idx_leads_created (created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
    id CHAR(16) PRIMARY KEY,
    type VARCHAR(10) NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    excerpt TEXT,
    body MEDIUMTEXT,
    author VARCHAR(255),
    cover_image TEXT,
    tags TEXT,
    status VARCHAR(10) NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    published_at DATETIME NULL,
    INDEX idx_posts_type_status (type, status),
    INDEX idx_posts_published (published_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  // Uploaded images, stored as blobs so they survive a git-deploy overwrite
  // and work identically across hosts (Hostinger staging, DigitalOcean prod).
  // post_id is informational only (which post an upload was made from); an
  // image can be reused (e.g. as an author avatar) independent of that.
  $pdo->exec("CREATE TABLE IF NOT EXISTS images (
    id CHAR(16) PRIMARY KEY,
    mime VARCHAR(60) NOT NULL,
    bytes LONGBLOB NOT NULL,
    alt VARCHAR(255),
    width INT NULL,
    height INT NULL,
    post_id CHAR(16) NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_images_post (post_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  // Reusable author profiles (name/bio/avatar) for blog & news posts.
  $pdo->exec("CREATE TABLE IF NOT EXISTS authors (
    id CHAR(16) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    bio TEXT,
    avatar_image_id CHAR(16) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  // Per-post view/engagement analytics (mirrors the whitepaper doc_engagement
  // pattern). One row per session; updated as the beacon reports more time.
  $pdo->exec("CREATE TABLE IF NOT EXISTS post_views (
    id CHAR(16) PRIMARY KEY,
    post_id CHAR(16) NOT NULL,
    session_key CHAR(40) NOT NULL,
    ip VARCHAR(64),
    country VARCHAR(64) NULL,
    referrer VARCHAR(255),
    seconds_spent INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_pv_post (post_id),
    INDEX idx_pv_session (post_id, session_key)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  // Reader comments. No login — name + email required (email encrypted like
  // leads). status decides visibility: approved (public), pending (held for
  // moderation because a filter flagged it), spam (hidden, kept for review).
  $pdo->exec("CREATE TABLE IF NOT EXISTS post_comments (
    id CHAR(16) PRIMARY KEY,
    post_id CHAR(16) NOT NULL,
    name VARCHAR(255) NOT NULL,
    email_bi CHAR(64),
    email_enc TEXT,
    body TEXT NOT NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'pending',
    ip VARCHAR(64),
    created_at DATETIME NOT NULL,
    INDEX idx_pc_post_status (post_id, status),
    INDEX idx_pc_created (created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  // Emoji-style reactions, deduped per reader via a hashed voter key
  // (hash of ip + post_id), not personally identifying on its own.
  $pdo->exec("CREATE TABLE IF NOT EXISTS post_reactions (
    id CHAR(16) PRIMARY KEY,
    post_id CHAR(16) NOT NULL,
    type VARCHAR(20) NOT NULL,
    voter_key CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_pr_voter (post_id, type, voter_key),
    INDEX idx_pr_post (post_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  // Single-row key/value store for admin settings (e.g. legacy TOTP secret).
  $pdo->exec("CREATE TABLE IF NOT EXISTS admin_settings (
    skey VARCHAR(64) PRIMARY KEY,
    sval TEXT,
    updated_at DATETIME NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  // Admin/staff user accounts. Email is the unique login id.
  $pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id CHAR(16) PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255),
    password_hash VARCHAR(255) NULL,
    totp_secret_enc TEXT NULL,
    mfa_enrolled TINYINT(1) NOT NULL DEFAULT 0,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    perm_content TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    reset_token_hash CHAR(64) NULL,
    reset_expires DATETIME NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_users_reset (reset_token_hash)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  // Qualification columns on leads (added defensively for upgrades).
  add_column_if_missing($pdo, 'leads', 'status', "VARCHAR(20) NOT NULL DEFAULT 'new'");
  add_column_if_missing($pdo, 'leads', 'lead_group', "VARCHAR(120) NULL");
  add_column_if_missing($pdo, 'leads', 'tags', "TEXT NULL");
  add_column_if_missing($pdo, 'leads', 'country', "VARCHAR(64) NULL");

  // Blog/news authoring additions: reusable author, SEO meta fields.
  // `author` (free-text) is kept as a fallback for posts created before the
  // Authors manager existed, and as a display fallback if author_id is unset.
  add_column_if_missing($pdo, 'posts', 'author_id', "CHAR(16) NULL");
  add_column_if_missing($pdo, 'posts', 'meta_title', "VARCHAR(255) NULL");
  add_column_if_missing($pdo, 'posts', 'meta_description', "VARCHAR(320) NULL");
  add_column_if_missing($pdo, 'posts', 'meta_keywords', "VARCHAR(500) NULL");

  // Password-reset columns (defensive — a users table created by an older build
  // may predate these, which would make issue_reset_token() throw a 500).
  add_column_if_missing($pdo, 'users', 'reset_token_hash', "CHAR(64) NULL");
  add_column_if_missing($pdo, 'users', 'reset_expires', "DATETIME NULL");

  // CRITICAL: an older build may have created reset_token_hash narrower than 64
  // chars. A sha256 hex digest is exactly 64 chars, so a narrower column would
  // SILENTLY TRUNCATE the stored hash on non-strict MySQL/MariaDB — making every
  // reset link fail the lookup with "invalid or expired". Widen it if needed.
  ensure_char_width($pdo, 'users', 'reset_token_hash', 64);

  seed_admin($pdo);
}

/** Add a column only if it doesn't already exist (portable across MySQL/MariaDB). */
function add_column_if_missing(PDO $pdo, $table, $column, $definition) {
  $stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
  );
  $stmt->execute([DB_NAME, $table, $column]);
  if ((int) $stmt->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
  }
}

/**
 * Guarantee a CHAR/VARCHAR column is at least $minLen wide, widening it in place
 * if an older schema created it too small. Without this, over-long values are
 * silently truncated on non-strict MySQL/MariaDB. Idempotent and cheap.
 */
function ensure_char_width(PDO $pdo, $table, $column, $minLen) {
  $stmt = $pdo->prepare(
    'SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
  );
  $stmt->execute([DB_NAME, $table, $column]);
  $len = $stmt->fetchColumn();
  if ($len !== false && $len !== null && (int) $len < $minLen) {
    $pdo->exec("ALTER TABLE `$table` MODIFY `$column` CHAR($minLen) NULL");
  }
}

/**
 * Ensure the default admin account exists. Uses ADMIN_ACCOUNT + the legacy
 * ADMIN_PASSWORD_HASH from config, and migrates any global TOTP secret.
 */
function seed_admin(PDO $pdo) {
  $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
  $stmt->execute([ADMIN_ACCOUNT]);
  if ((int) $stmt->fetchColumn() > 0) return;

  // Migrate a previously-enrolled global TOTP secret, if present.
  $legacyTotp = null; $mfaEnrolled = 0;
  $s = $pdo->prepare('SELECT sval FROM admin_settings WHERE skey = ?');
  $s->execute(['totp_secret']);
  if ($row = $s->fetch()) { $legacyTotp = $row['sval']; $mfaEnrolled = 1; }

  $ins = $pdo->prepare(
    'INSERT INTO users (id, email, name, password_hash, totp_secret_enc, mfa_enrolled,
       is_admin, perm_content, active, created_at)
     VALUES (?, ?, ?, ?, ?, ?, 1, 1, 1, NOW())'
  );
  $ins->execute([
    db_new_id(), ADMIN_ACCOUNT, 'Administrator',
    defined('ADMIN_PASSWORD_HASH') ? ADMIN_PASSWORD_HASH : null,
    $legacyTotp, $mfaEnrolled,
  ]);
}

function setting_get($key) {
  $stmt = db()->prepare('SELECT sval FROM admin_settings WHERE skey = ?');
  $stmt->execute([$key]);
  $row = $stmt->fetch();
  return $row ? $row['sval'] : null;
}

function setting_set($key, $value) {
  $stmt = db()->prepare(
    'INSERT INTO admin_settings (skey, sval, updated_at) VALUES (?, ?, NOW())
     ON DUPLICATE KEY UPDATE sval = VALUES(sval), updated_at = NOW()'
  );
  $stmt->execute([$key, $value]);
}

function db_new_id() {
  return bin2hex(random_bytes(8));
}
