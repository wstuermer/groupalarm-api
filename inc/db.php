<?php

declare(strict_types=1);

/**
 * Returns a shared PDO connection, created lazily on first call.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    return $pdo;
}

/**
 * True if the `users` table doesn't exist yet, i.e. db/schema.sql hasn't been imported.
 */
function db_is_uninitialized(): bool
{
    try {
        db()->query('SELECT 1 FROM users LIMIT 1');
        return false;
    } catch (PDOException $e) {
        return true;
    }
}
