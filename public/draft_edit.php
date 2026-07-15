<?php

declare(strict_types=1);

require __DIR__ . '/../inc/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $rowId = (string) ($_POST['row_id'] ?? '');
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete') {
        draft_delete_row($rowId);
        flash_set('success', 'Zeile gelöscht.');
        header('Location: dashboard.php');
        exit;
    }

    if ($action === 'update') {
        $fields = [
            'date' => trim((string) ($_POST['date'] ?? '')),
            'start_time' => trim((string) ($_POST['start_time'] ?? '')),
            'end_time' => trim((string) ($_POST['end_time'] ?? '')),
            'name' => trim((string) ($_POST['name'] ?? '')),
            'description' => (string) ($_POST['description'] ?? ''),
        ];
        draft_update_row($rowId, $fields);
        flash_set('success', 'Zeile aktualisiert.');
        header('Location: dashboard.php');
        exit;
    }

    header('Location: dashboard.php');
    exit;
}

$rowId = (string) ($_GET['row_id'] ?? '');
$index = draft_find_index($rowId);

if ($index === null) {
    flash_set('error', 'Zeile nicht gefunden (evtl. schon gelöscht/gesendet).');
    header('Location: dashboard.php');
    exit;
}

$row = draft_get_all()[$index];

$title = 'Termin bearbeiten';
require __DIR__ . '/../templates/header.php';
?>
<h1>Termin bearbeiten</h1>

<?php if ($row['errors']): ?>
<div class="flash flash-error">
    <ul class="row-errors"><?php foreach ($row['errors'] as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form class="card" method="post" action="draft_edit.php">
    <?php csrf_field(); ?>
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="row_id" value="<?= h($row['row_id']) ?>">

    <label for="date">Datum</label>
    <input type="date" id="date" name="date" value="<?= h($row['date']) ?>" required autofocus>

    <label for="start_time">Startzeit</label>
    <input type="time" id="start_time" name="start_time" value="<?= h($row['start_time']) ?>" required>

    <label for="end_time">Endzeit</label>
    <input type="time" id="end_time" name="end_time" value="<?= h($row['end_time']) ?>" required>

    <label for="name">Betreff</label>
    <input type="text" id="name" name="name" value="<?= h($row['name']) ?>" required>

    <label for="description">Beschreibung</label>
    <textarea id="description" name="description" required><?= h($row['description']) ?></textarea>

    <button type="submit">Speichern</button>
</form>
<p><a href="dashboard.php">Zurück zur Übersicht</a></p>
<?php
require __DIR__ . '/../templates/footer.php';
