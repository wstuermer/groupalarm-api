<?php
/** @var string|null $title */
$user = current_user();
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title ?? 'Groupalarm Terminverwaltung') ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-brand">Groupalarm Terminverwaltung</div>
    <?php if ($user): ?>
    <nav class="topbar-nav">
        <a href="dashboard.php">Entwürfe</a>
        <a href="add_appointment.php">Neuer Termin</a>
        <a href="upload.php">Datei hochladen</a>
        <a href="settings.php">Einstellungen</a>
        <?php if ($user['role'] === 'admin'): ?>
        <a href="admin_users.php">Benutzer</a>
        <?php endif; ?>
        <span class="topbar-user"><?= h($user['email']) ?></span>
        <a href="logout.php">Abmelden</a>
    </nav>
    <?php endif; ?>
</header>
<main class="content">
<?php require __DIR__ . '/flash.php'; ?>
