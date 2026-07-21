<?php

declare(strict_types=1);

require __DIR__ . '/../inc/bootstrap.php';
require_login();

$userId = (int) current_user()['id'];
$defaultLabelIds = groupalarm_get_label_ids($userId);
$defaultReminderMinutes = groupalarm_get_default_reminder_minutes($userId);

$fields = [
    'date' => '',
    'start_time' => DEFAULT_APPOINTMENT_START_TIME,
    'end_time' => DEFAULT_APPOINTMENT_END_TIME,
    'name' => DEFAULT_APPOINTMENT_NAME,
    'description' => '',
    'label_ids' => $defaultLabelIds,
    'reminder_minutes' => $defaultReminderMinutes,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $labelsUnavailable = ($_POST['label_ids_unavailable'] ?? '') === '1';
    $postedLabelIds = $_POST['label_ids'] ?? [];

    $fields = [
        'date' => trim((string) ($_POST['date'] ?? '')),
        'start_time' => trim((string) ($_POST['start_time'] ?? '')) ?: DEFAULT_APPOINTMENT_START_TIME,
        'end_time' => trim((string) ($_POST['end_time'] ?? '')) ?: DEFAULT_APPOINTMENT_END_TIME,
        'name' => trim((string) ($_POST['name'] ?? '')) ?: DEFAULT_APPOINTMENT_NAME,
        'description' => (string) ($_POST['description'] ?? ''),
        'label_ids' => $labelsUnavailable
            ? $defaultLabelIds
            : array_values(array_map('intval', is_array($postedLabelIds) ? $postedLabelIds : [])),
        'reminder_minutes' => normalize_reminder_minutes($_POST['reminder_minutes'] ?? ''),
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

$labelsResult = groupalarm_get_labels_for_user($userId);
$availableLabels = $labelsResult['labels'];

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

    <label for="name">Betreff</label>
    <input type="text" id="name" name="name" value="<?= h($fields['name']) ?>" required>
    <p class="field-hint">Standard ist "<?= h(DEFAULT_APPOINTMENT_NAME) ?>", kann hier frei geändert werden.</p>

    <label for="description">Beschreibung</label>
    <textarea id="description" name="description" required><?= h($fields['description']) ?></textarea>
    <p class="field-hint">Mehrzeilig möglich (einfach Zeilenumbrüche verwenden).</p>

    <label for="reminder_minutes">Erinnerung</label>
    <select id="reminder_minutes" name="reminder_minutes">
        <?php foreach (REMINDER_OPTIONS as $value => $label): ?>
        <option value="<?= h((string) $value) ?>" <?= $value === ($fields['reminder_minutes'] ?? '') ? 'selected' : '' ?>>
            <?= h($label) ?>
        </option>
        <?php endforeach; ?>
    </select>
    <p class="field-hint">Vorbelegt mit der Standard-Erinnerung aus den Einstellungen.</p>

    <label for="label_ids">Labels</label>
    <?php if ($availableLabels): ?>
        <select id="label_ids" name="label_ids[]" multiple size="8">
            <?php foreach ($availableLabels as $label): ?>
            <option value="<?= h((string) $label['id']) ?>" <?= in_array($label['id'], $fields['label_ids'], true) ? 'selected' : '' ?>>
                <?= h($label['name']) ?> (#<?= h((string) $label['id']) ?>)
            </option>
            <?php endforeach; ?>
        </select>
        <p class="field-hint">Mehrfachauswahl möglich (Strg/Cmd gedrückt halten). Vorbelegt mit den Standard-Labels aus den Einstellungen.</p>
    <?php else: ?>
        <select id="label_ids" name="label_ids[]" multiple size="8" disabled></select>
        <input type="hidden" name="label_ids_unavailable" value="1">
        <p class="field-hint field-warning"><?= h($labelsResult['error'] ?? 'Labels konnten nicht von Groupalarm geladen werden.') ?></p>
    <?php endif; ?>

    <button type="submit">Zur Entwurfsliste hinzufügen</button>
</form>
<p><a href="dashboard.php">Zurück zur Übersicht</a></p>
<?php
require __DIR__ . '/../templates/footer.php';
