<?php

declare(strict_types=1);

require __DIR__ . '/../inc/bootstrap.php';
require_login();

$userId = (int) current_user()['id'];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $file = $_FILES['appointments_file'] ?? null;

    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Bitte eine Datei auswählen.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Datei-Upload fehlgeschlagen (Fehlercode ' . $file['error'] . ').';
    } elseif ($file['size'] > 1_000_000) {
        $error = 'Datei ist zu groß (Limit 1 MB).';
    } else {
        $contents = file_get_contents($file['tmp_name']);
        if ($contents === false) {
            $error = 'Datei konnte nicht gelesen werden.';
        } else {
            $rows = parse_appointments_text(
                $contents,
                groupalarm_get_label_ids($userId),
                groupalarm_get_default_reminder_minutes($userId)
            );
            if (!$rows) {
                $error = 'Datei enthält keine verwertbaren Zeilen (nur Kommentare/Leerzeilen?).';
            } else {
                draft_add_rows($rows);
                $errorCount = count(array_filter($rows, fn (array $r) => !empty($r['errors'])));
                $message = count($rows) . ' Zeile(n) zur Entwurfsliste hinzugefügt.';
                if ($errorCount > 0) {
                    $message .= " {$errorCount} davon mit Fehlern - bitte auf der Übersicht korrigieren.";
                }
                flash_set($errorCount > 0 ? 'error' : 'success', $message);
                header('Location: dashboard.php');
                exit;
            }
        }
    }
}

$title = 'Datei hochladen';
require __DIR__ . '/../templates/header.php';
?>
<h1>appointments.txt hochladen</h1>
<p>
    Format: <code>YYYY-MM-DD[ HH:MM-HH:MM] Beschreibung</code> - eine Zeile pro Termin.
    Ohne Zeitangabe gelten <?= h(DEFAULT_APPOINTMENT_START_TIME) ?>-<?= h(DEFAULT_APPOINTMENT_END_TIME) ?> Uhr als Standard.
    Der Betreff steht nicht in der Datei - jede hochgeladene Zeile bekommt automatisch
    den Betreff "<?= h(DEFAULT_APPOINTMENT_NAME) ?>", der sich in der Entwurfsliste bei
    Bedarf pro Termin ändern lässt.
    Zeilen mit <code>#</code> am Anfang sowie Leerzeilen werden ignoriert.
    Die hochgeladenen Zeilen werden <strong>nicht sofort gesendet</strong>, sondern erst
    in der Entwurfsliste zur Prüfung angezeigt.
</p>

<?php if ($error): ?>
<div class="flash flash-error"><?= h($error) ?></div>
<?php endif; ?>

<form class="card" method="post" action="upload.php" enctype="multipart/form-data">
    <?php csrf_field(); ?>
    <label for="appointments_file">Datei (appointments.txt)</label>
    <input type="file" id="appointments_file" name="appointments_file" accept=".txt,text/plain" required>
    <button type="submit">Hochladen</button>
</form>
<p><a href="dashboard.php">Zurück zur Übersicht</a></p>
<?php
require __DIR__ . '/../templates/footer.php';
