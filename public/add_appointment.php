<?php

declare(strict_types=1);

require __DIR__ . '/../inc/bootstrap.php';
require_login();

$fields = [
    'date' => '',
    'start_time' => DEFAULT_APPOINTMENT_START_TIME,
    'end_time' => DEFAULT_APPOINTMENT_END_TIME,
    'name' => DEFAULT_APPOINTMENT_NAME,
    'description' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $fields = [
        'date' => trim((string) ($_POST['date'] ?? '')),
        'start_time' => trim((string) ($_POST['start_time'] ?? '')) ?: DEFAULT_APPOINTMENT_START_TIME,
        'end_time' => trim((string) ($_POST['end_time'] ?? '')) ?: DEFAULT_APPOINTMENT_END_TIME,
        'name' => trim((string) ($_POST['name'] ?? '')) ?: DEFAULT_APPOINTMENT_NAME,
        'description' => (string) ($_POST['description'] ?? ''),
    ];

    $row = make_draft_row($fields, 'manual');
    draft_add_row($row);

    if ($row['errors']) {
        flash_set('error', 'Termin wurde mit Fehlern zur Liste hinzugefügt - bitte auf der Übersicht korrigieren.');
    } else {
        flash_set('success', 'Termin zur Entwurfsliste hinzugefügt.');
    }

    header('Location: dashboard.php');
    exit;
}

$title = 'Termin hinzufügen';
require __DIR__ . '/../templates/header.php';
?>
<h1>Termin hinzufügen</h1>

<form class="card" method="post" action="add_appointment.php">
    <?php csrf_field(); ?>

    <label for="date">Datum</label>
    <input type="date" id="date" name="date" value="<?= h($fields['date']) ?>" required autofocus>

    <label for="start_time">Startzeit</label>
    <input type="time" id="start_time" name="start_time" value="<?= h($fields['start_time']) ?>" required>

    <label for="end_time">Endzeit</label>
    <input type="time" id="end_time" name="end_time" value="<?= h($fields['end_time']) ?>" required>

    <label for="name">Titel</label>
    <input type="text" id="name" name="name" value="<?= h($fields['name']) ?>" required>

    <label for="description">Beschreibung</label>
    <textarea id="description" name="description" required><?= h($fields['description']) ?></textarea>
    <p class="field-hint">Mehrzeilig möglich (einfach Zeilenumbrüche verwenden).</p>

    <button type="submit">Zur Entwurfsliste hinzufügen</button>
</form>
<p><a href="dashboard.php">Zurück zur Übersicht</a></p>
<?php
require __DIR__ . '/../templates/footer.php';
