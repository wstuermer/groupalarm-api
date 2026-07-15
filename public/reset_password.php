<?php

declare(strict_types=1);

require __DIR__ . '/../inc/bootstrap.php';

$rawToken = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$tokenRow = $rawToken !== '' ? find_valid_password_reset($rawToken) : null;

if ($tokenRow === null) {
    $title = 'Link ungültig';
    require __DIR__ . '/../templates/header.php';
    ?>
    <h1>Link ungültig oder abgelaufen</h1>
    <p>Dieser Link ist ungültig, wurde bereits verwendet, oder ist abgelaufen.</p>
    <p><a href="forgot_password.php">Neuen Link anfordern</a></p>
    <?php
    require __DIR__ . '/../templates/footer.php';
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if (strlen($password) < 10) {
        $errors[] = 'Passwort muss mindestens 10 Zeichen lang sein.';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'Passwörter stimmen nicht überein.';
    }

    if (!$errors) {
        complete_password_reset((int) $tokenRow['id'], (int) $tokenRow['user_id'], $password);
        flash_set('success', 'Passwort gesetzt. Bitte melde dich an.');
        header('Location: login.php');
        exit;
    }
}

$title = 'Neues Passwort setzen';
require __DIR__ . '/../templates/header.php';
?>
<h1>Neues Passwort setzen</h1>

<?php if ($errors): ?>
<div class="flash flash-error"><?= implode('<br>', array_map('h', $errors)) ?></div>
<?php endif; ?>

<form class="card" method="post" action="reset_password.php">
    <?php csrf_field(); ?>
    <input type="hidden" name="token" value="<?= h($rawToken) ?>">

    <label for="password">Neues Passwort</label>
    <input type="password" id="password" name="password" minlength="10" required autofocus>

    <label for="password_confirm">Passwort bestätigen</label>
    <input type="password" id="password_confirm" name="password_confirm" minlength="10" required>

    <button type="submit">Passwort setzen</button>
</form>
<?php
require __DIR__ . '/../templates/footer.php';
