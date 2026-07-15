<?php

declare(strict_types=1);

require __DIR__ . '/../inc/bootstrap.php';

if (db_is_uninitialized()) {
    http_response_code(500);
    echo 'Datenbank nicht initialisiert. Bitte db/schema.sql importieren.';
    exit;
}

$stmt = db()->query('SELECT COUNT(*) AS c FROM users');
if ((int) $stmt->fetch()['c'] === 0) {
    header('Location: setup_admin.php');
    exit;
}

header('Location: ' . (is_logged_in() ? 'dashboard.php' : 'login.php'));
exit;
