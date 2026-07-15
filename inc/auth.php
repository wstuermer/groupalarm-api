<?php

declare(strict_types=1);

/**
 * Returns the logged-in user's row (without password_hash) or null if not logged in.
 * Cached per-request since several guards/pages call this.
 */
function current_user(): ?array
{
    static $user = false; // false = "not yet looked up", null = "looked up, no user"

    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $stmt = db()->prepare('SELECT id, email, role, is_active FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $row = $stmt->fetch();
            if ($row && (int) $row['is_active'] === 1) {
                $user = $row;
            } else {
                // Account deactivated/deleted since the session was created.
                session_unset();
                session_destroy();
            }
        }
    }

    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === 'admin';
}

/**
 * Redirects to login.php unless a valid session exists. Call at the top of every
 * page that requires authentication.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        flash_set('error', 'Diese Aktion erfordert Admin-Rechte.');
        header('Location: dashboard.php');
        exit;
    }
}

/**
 * Attempts a login. On success, regenerates the session ID (privilege change)
 * and records last_login_at. Returns true/false; never throws for bad credentials.
 */
function attempt_login(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT id, password_hash, is_active FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if (!$row || (int) $row['is_active'] !== 1 || !password_verify($password, $row['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $row['id'];
    $_SESSION['last_activity'] = time();

    $update = db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
    $update->execute([$row['id']]);

    return true;
}

function logout(): void
{
    $_SESSION = [];
    session_unset();
    session_destroy();
}

/**
 * Creates a password_resets row of the given type ('reset' or 'invite') for a user
 * and returns the raw token (only ever exists in memory / the emailed link - only
 * its sha256 hash is stored). Used by forgot_password.php (type=reset, 10 min) and
 * admin_users.php's "create user" flow (type=invite, 48h).
 */
function create_password_reset_token(int $userId, string $type): string
{
    $rawToken = bin2hex(random_bytes(32));
    // Both TTLs normalized to whole minutes - INTERVAL ? HOUR with a fractional
    // value (e.g. 10/60 = 0.1667) gets truncated to 0 by MariaDB, which previously
    // made every "reset" token expire at the same instant it was created.
    $minutes = $type === 'invite' ? INVITE_TTL_HOURS * 60 : PASSWORD_RESET_TTL_MINUTES;

    $stmt = db()->prepare(
        'INSERT INTO password_resets (user_id, token_hash, type, expires_at)
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))'
    );
    $stmt->execute([$userId, hash('sha256', $rawToken), $type, $minutes]);

    return $rawToken;
}

/**
 * Validates a raw token against password_resets: matching hash, not expired, not used.
 * Returns the row (with user_id, type) or null if invalid/expired/already used.
 */
function find_valid_password_reset(string $rawToken): ?array
{
    $stmt = db()->prepare(
        'SELECT id, user_id, type FROM password_resets
         WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()'
    );
    $stmt->execute([hash('sha256', $rawToken)]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/**
 * Sets a new password for a user, marks the given reset token used, and invalidates
 * every other outstanding (unused) token for that user - both 'reset' and 'invite',
 * since a freshly-chosen password makes any other pending link moot.
 */
function complete_password_reset(int $tokenId, int $userId, string $newPassword): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
        $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')
            ->execute([$tokenId]);
        $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL AND id != ?')
            ->execute([$userId, $tokenId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
