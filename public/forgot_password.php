<?php

declare(strict_types=1);

require __DIR__ . '/../inc/bootstrap.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$genericMessage = 'Falls diese Adresse existiert, wurde eine E-Mail mit einem Link verschickt.';
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email = trim((string) ($_POST['email'] ?? ''));
    $stmt = db()->prepare('SELECT id FROM users WHERE email = ? AND is_active = 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $rawToken = create_password_reset_token((int) $user['id'], 'reset');
        mail_send_reset_link($email, $rawToken, 'reset');
    }

    // Same message and same code path regardless of whether the address matched,
    // so this endpoint can't be used to enumerate registered emails.
    $submitted = true;
}

$title = 'Passwort vergessen';
require __DIR__ . '/../templates/header.php';
?>
<h1>Passwort vergessen</h1>

<?php if ($submitted): ?>
<div class="flash flash-success"><?= h($genericMessage) ?></div>
<?php else: ?>
<form class="card" method="post" action="forgot_password.php">
    <?php csrf_field(); ?>
    <label for="email">E-Mail</label>
    <input type="email" id="email" name="email" required autofocus>
    <button type="submit">Link anfordern</button>
</form>
<?php endif; ?>
<p><a href="login.php">Zurück zum Login</a></p>
<?php
require __DIR__ . '/../templates/footer.php';
