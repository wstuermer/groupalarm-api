<?php

declare(strict_types=1);

/**
 * Returns the current session's CSRF token, generating one on first use.
 * One token per session (not per-form/single-use) so several draft-review
 * forms open on dashboard.php at once don't invalidate each other.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Echoes a hidden CSRF input for use inside a <form>.
 */
function csrf_field(): void
{
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verifies $_POST['csrf_token'] against the session token. Aborts the request
 * with a 400 and a flash message on mismatch rather than returning a bool,
 * since every POST handler needs to do this and forgetting the check-and-bail
 * is a much easier mistake than forgetting to call a function at all.
 */
function csrf_verify(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(400);
        flash_set('error', 'Sitzung abgelaufen, bitte erneut versuchen.');
        $referer = $_SERVER['HTTP_REFERER'] ?? (APP_BASE_URL . '/dashboard.php');
        header('Location: ' . $referer);
        exit;
    }
}
