<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php-error.log');

$configPath = __DIR__ . '/../config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    echo 'config.php fehlt. Bitte config.php.example nach config.php kopieren und ausfüllen.';
    exit;
}
require_once $configPath;

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/view.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/draft_store.php';
require_once __DIR__ . '/txt_parser.php';
require_once __DIR__ . '/groupalarm_client.php';

// --- Hardened session, started before any output ---
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}
session_name('galarm_sess');
session_start();

// --- Idle timeout, on top of session.gc_maxlifetime ---
if (!empty($_SESSION['user_id'])) {
    $lastActivity = $_SESSION['last_activity'] ?? 0;
    if (time() - $lastActivity > SESSION_IDLE_TIMEOUT_SECONDS) {
        logout();
    } else {
        $_SESSION['last_activity'] = time();
    }
}
