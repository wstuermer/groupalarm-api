<?php

declare(strict_types=1);

/**
 * Shorthand for htmlspecialchars() with sane defaults, for use in templates.
 */
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Queues a one-time flash message (survives exactly one redirect).
 * $type is 'success' or 'error'.
 */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Pops and returns all queued flash messages; the templates/flash.php partial renders these.
 */
function flash_take(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}
