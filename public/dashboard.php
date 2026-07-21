<?php

declare(strict_types=1);

require __DIR__ . '/../inc/bootstrap.php';
require_login();

$user = current_user();
$userId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_all') {
    csrf_verify();

    $settingsRow = groupalarm_get_settings_row($userId);
    $organizationId = $settingsRow['organization_id'] ?? null;
    $token = groupalarm_get_decrypted_token($userId);

    $configErrors = validate_user_groupalarm_config(
        $organizationId !== null ? (int) $organizationId : null,
        $token
    );

    if ($configErrors) {
        flash_set('error', implode(' ', $configErrors));
        header('Location: dashboard.php');
        exit;
    }

    // Defensive re-validation: a row that looked fine when added may no longer be
    // (this call is cheap and catches any drift, e.g. session tampering).
    $allRows = draft_get_all();
    $readyRows = [];
    foreach ($allRows as $row) {
        $row['errors'] = validate_draft_row($row);
        if (empty($row['errors'])) {
            $readyRows[] = $row;
        }
    }

    if (!$readyRows) {
        flash_set('error', 'Keine fehlerfreien Zeilen zum Senden vorhanden.');
        header('Location: dashboard.php');
        exit;
    }

    $results = groupalarm_send_batch((string) $token, (int) $organizationId, $readyRows);

    $successRowIds = [];
    $insertLog = db()->prepare(
        'INSERT INTO appointment_log
            (user_id, groupalarm_appointment_id, name, description, start_date_local, end_date_local,
             organization_id, label_ids_json, status, http_status, error_message, request_payload_json)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($results as $rowId => $result) {
        $row = $result['row'];
        $payload = $result['payload'];
        $startLocal = $row['date'] . ' ' . $row['start_time'] . ':00';
        $endLocal = $row['date'] . ' ' . $row['end_time'] . ':00';
        $groupalarmId = $result['response']['id'] ?? null;

        $insertLog->execute([
            $userId,
            $groupalarmId,
            $payload['name'],
            $payload['description'],
            $startLocal,
            $endLocal,
            $organizationId,
            json_encode($row['label_ids']),
            $result['success'] ? 'success' : 'error',
            $result['http_status'],
            $result['error'],
            json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        if ($result['success']) {
            $successRowIds[] = $rowId;
        } else {
            draft_update_row($rowId, ['errors' => ['Groupalarm: ' . $result['error']]]);
        }
    }

    draft_remove_rows($successRowIds);

    $successCount = count($successRowIds);
    $failCount = count($readyRows) - $successCount;
    $skippedCount = count($allRows) - count($readyRows);

    $summary = "{$successCount} von " . count($readyRows) . ' Terminen erfolgreich gesendet.';
    if ($failCount > 0) {
        $summary .= " {$failCount} fehlgeschlagen (siehe Fehler unten).";
    }
    if ($skippedCount > 0) {
        $summary .= " {$skippedCount} Zeile(n) mit Fehlern wurden übersprungen.";
    }
    flash_set($failCount > 0 || $skippedCount > 0 ? 'error' : 'success', $summary);

    header('Location: dashboard.php');
    exit;
}

$draft = draft_get_all();
$labelsResult = groupalarm_get_labels_for_user($userId);
$labelNameMap = groupalarm_label_name_map($labelsResult['labels']);

$title = 'Entwürfe';
require __DIR__ . '/../templates/header.php';
?>
<h1>Termine im Entwurf</h1>
<p>
    <a href="add_appointment.php" class="btn">+ Termin hinzufügen</a>
    <a href="upload.php" class="btn btn-secondary">Datei hochladen</a>
</p>

<?php if (!$labelsResult['success'] && empty($labelNameMap)): ?>
<p class="field-hint field-warning">Label-Namen konnten nicht von Groupalarm geladen werden - IDs werden angezeigt.</p>
<?php endif; ?>

<?php if (!$draft): ?>
<p>Keine Entwürfe vorhanden. Füge einen Termin hinzu oder lade eine appointments.txt hoch.</p>
<?php else: ?>
<form method="post" action="dashboard.php" data-confirm="Alle fehlerfreien Termine jetzt an Groupalarm senden?">
    <?php csrf_field(); ?>
    <input type="hidden" name="action" value="send_all">
    <table>
        <thead>
            <tr>
                <th>Datum</th>
                <th>Start</th>
                <th>Ende</th>
                <th>Betreff</th>
                <th>Beschreibung</th>
                <th>Labels</th>
                <th>Erinnerung</th>
                <th>Quelle</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($draft as $row): ?>
            <tr class="<?= $row['errors'] ? 'row-error' : '' ?>">
                <td><?= h($row['date']) ?></td>
                <td><?= h($row['start_time']) ?></td>
                <td><?= h($row['end_time']) ?></td>
                <td><?= h($row['name']) ?></td>
                <td><?= nl2br(h($row['description'])) ?></td>
                <td><?= h(implode(', ', array_map(
                    fn (int $id) => $labelNameMap[$id] ?? ('#' . $id),
                    $row['label_ids'] ?? []
                ))) ?></td>
                <td><?= h(reminder_option_label($row['reminder_minutes'] ?? null)) ?></td>
                <td><?= $row['source'] === 'upload' ? 'Upload' . ($row['line_number'] ? " (Zeile {$row['line_number']})" : '') : 'Manuell' ?></td>
                <td class="actions-row">
                    <a href="draft_edit.php?row_id=<?= urlencode($row['row_id']) ?>">Bearbeiten</a>
                    <button type="submit" form="delete-form-<?= h($row['row_id']) ?>" class="btn-danger">Löschen</button>
                </td>
            </tr>
            <?php if ($row['errors']): ?>
            <tr class="row-error">
                <td colspan="9">
                    <ul class="row-errors">
                        <?php foreach ($row['errors'] as $error): ?>
                        <li><?= h($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </td>
            </tr>
            <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
    <button type="submit">Alle fehlerfreien Termine senden</button>
</form>

<?php foreach ($draft as $row): ?>
<form id="delete-form-<?= h($row['row_id']) ?>" method="post" action="draft_edit.php"
      data-confirm="Diese Zeile löschen?" style="display:none">
    <?php csrf_field(); ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="row_id" value="<?= h($row['row_id']) ?>">
</form>
<?php endforeach; ?>
<?php endif; ?>
<?php
require __DIR__ . '/../templates/footer.php';
