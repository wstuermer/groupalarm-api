<?php

declare(strict_types=1);

require __DIR__ . '/../inc/bootstrap.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (attempt_login($email, $password)) {
        header('Location: dashboard.php');
        exit;
    }

    $errors[] = 'E-Mail oder Passwort falsch, oder Account deaktiviert.';
}

$title = 'Anmelden';
require __DIR__ . '/../templates/header.php';
?>
<h1>Anmelden</h1>

<?php if ($errors): ?>
<div class="flash flash-error"><?= implode('<br>', array_map('h', $errors)) ?></div>
<?php endif; ?>

<form class="card" method="post" action="login.php">
    <?php csrf_field(); ?>
    <label for="email">E-Mail</label>
    <input type="email" id="email" name="email" value="<?= h($email) ?>" required autofocus>

    <label for="password">Passwort</label>
    <input type="password" id="password" name="password" required>

    <button type="submit">Anmelden</button>
</form>
<p><a href="forgot_password.php">Passwort vergessen?</a></p>
<?php
require __DIR__ . '/../templates/footer.php';
