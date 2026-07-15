<?php

declare(strict_types=1);

require __DIR__ . '/../inc/bootstrap.php';

function users_table_empty(): bool
{
    $stmt = db()->query('SELECT COUNT(*) AS c FROM users');
    return (int) $stmt->fetch()['c'] === 0;
}

if (db_is_uninitialized()) {
    http_response_code(500);
    echo 'Datenbank nicht initialisiert. Bitte db/schema.sql importieren.';
    exit;
}

// Re-checked on both GET and POST: closes the setup window for good the moment
// the first admin exists, even if someone bookmarks or re-submits this page.
if (!users_table_empty()) {
    header('Location: login.php');
    exit;
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Bitte eine gültige E-Mail-Adresse angeben.';
    }
    if (strlen($password) < 10) {
        $errors[] = 'Passwort muss mindestens 10 Zeichen lang sein.';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'Passwörter stimmen nicht überein.';
    }

    if (!$errors && !users_table_empty()) {
        // Lost the race - someone else finished setup in the meantime.
        header('Location: login.php');
        exit;
    }

    if (!$errors) {
        $stmt = db()->prepare(
            'INSERT INTO users (email, password_hash, role, is_active) VALUES (?, ?, "admin", 1)'
        );
        $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) db()->lastInsertId();
        $_SESSION['last_activity'] = time();

        flash_set('success', 'Admin-Account angelegt. Willkommen!');
        header('Location: dashboard.php');
        exit;
    }
}

$title = 'Ersten Admin anlegen';
require __DIR__ . '/../templates/header.php';
?>
<h1>Ersten Admin-Account anlegen</h1>
<p>Es existiert noch kein Benutzer. Lege hier den ersten (Admin-)Account an.</p>

<?php if ($errors): ?>
<div class="flash flash-error"><?= implode('<br>', array_map('h', $errors)) ?></div>
<?php endif; ?>

<form class="card" method="post" action="setup_admin.php">
    <?php csrf_field(); ?>
    <label for="email">E-Mail</label>
    <input type="email" id="email" name="email" value="<?= h($email) ?>" required>

    <label for="password">Passwort</label>
    <input type="password" id="password" name="password" minlength="10" required>

    <label for="password_confirm">Passwort bestätigen</label>
    <input type="password" id="password_confirm" name="password_confirm" minlength="10" required>

    <button type="submit">Admin anlegen</button>
</form>
<?php
require __DIR__ . '/../templates/footer.php';
