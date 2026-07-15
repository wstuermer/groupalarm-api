<?php

declare(strict_types=1);

require __DIR__ . '/../inc/bootstrap.php';
require_login();

$user = current_user();
$userId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'change_password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $newConfirm = (string) ($_POST['new_password_confirm'] ?? '');

        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($current, (string) $hash)) {
            flash_set('error', 'Aktuelles Passwort ist falsch.');
        } elseif (strlen($new) < 10) {
            flash_set('error', 'Neues Passwort muss mindestens 10 Zeichen lang sein.');
        } elseif ($new !== $newConfirm) {
            flash_set('error', 'Neue Passwörter stimmen nicht überein.');
        } else {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
            flash_set('success', 'Passwort geändert.');
        }
    } elseif ($action === 'update_groupalarm') {
        $organizationId = (string) ($_POST['organization_id'] ?? '');
        $labelIdsRaw = (string) ($_POST['label_ids'] ?? '');

        $labelIds = array_values(array_filter(array_map(
            fn (string $s) => (int) trim($s),
            preg_split('/[,\s]+/', $labelIdsRaw) ?: []
        ), fn (int $id) => $id > 0));

        if (!ctype_digit($organizationId) || (int) $organizationId <= 0) {
            flash_set('error', 'Organisation-ID muss eine positive Zahl sein.');
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            $pdo->prepare(
                'INSERT INTO groupalarm_settings (user_id, organization_id) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE organization_id = VALUES(organization_id)'
            )->execute([$userId, (int) $organizationId]);

            $pdo->prepare('DELETE FROM user_labels WHERE user_id = ?')->execute([$userId]);
            $insertLabel = $pdo->prepare(
                'INSERT INTO user_labels (user_id, label_id, sort_order) VALUES (?, ?, ?)'
            );
            foreach ($labelIds as $order => $labelId) {
                $insertLabel->execute([$userId, $labelId, $order]);
            }
            $pdo->commit();

            flash_set('success', 'Organisation/Labels gespeichert.');
        }
    } elseif ($action === 'update_token') {
        $token = trim((string) ($_POST['api_token'] ?? ''));

        if ($token === '') {
            flash_set('error', 'Bitte einen Token eingeben.');
        } else {
            $encrypted = groupalarm_encrypt_token($token);
            db()->prepare(
                'INSERT INTO groupalarm_settings (user_id, api_token_ciphertext, api_token_nonce, api_token_tag)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    api_token_ciphertext = VALUES(api_token_ciphertext),
                    api_token_nonce = VALUES(api_token_nonce),
                    api_token_tag = VALUES(api_token_tag)'
            )->execute([$userId, $encrypted['ciphertext'], $encrypted['nonce'], $encrypted['tag']]);

            flash_set('success', 'API-Token gespeichert.');
        }
    }

    header('Location: settings.php');
    exit;
}

$settingsRow = groupalarm_get_settings_row($userId);
$organizationId = $settingsRow['organization_id'] ?? '';
$labelIds = groupalarm_get_label_ids($userId);
$hasToken = $settingsRow !== null && $settingsRow['api_token_ciphertext'] !== null;
$tokenUpdatedAt = $hasToken ? $settingsRow['updated_at'] : null;

$title = 'Einstellungen';
require __DIR__ . '/../templates/header.php';
?>
<h1>Einstellungen</h1>

<h2>Passwort ändern</h2>
<form class="card" method="post" action="settings.php">
    <?php csrf_field(); ?>
    <input type="hidden" name="action" value="change_password">

    <label for="current_password">Aktuelles Passwort</label>
    <input type="password" id="current_password" name="current_password" required>

    <label for="new_password">Neues Passwort</label>
    <input type="password" id="new_password" name="new_password" minlength="10" required>

    <label for="new_password_confirm">Neues Passwort bestätigen</label>
    <input type="password" id="new_password_confirm" name="new_password_confirm" minlength="10" required>

    <button type="submit">Passwort ändern</button>
</form>

<h2>Groupalarm Organisation &amp; Labels</h2>
<form class="card" method="post" action="settings.php">
    <?php csrf_field(); ?>
    <input type="hidden" name="action" value="update_groupalarm">

    <label for="organization_id">Organisation-ID</label>
    <input type="number" id="organization_id" name="organization_id" value="<?= h((string) $organizationId) ?>" min="1" required>

    <label for="label_ids">Label-IDs</label>
    <input type="text" id="label_ids" name="label_ids" value="<?= h(implode(', ', $labelIds)) ?>" placeholder="z.B. 21868, 21840, 21832">
    <p class="field-hint">Kommagetrennte Liste der Label-IDs, die jedem neuen Termin zugeordnet werden.</p>

    <button type="submit">Speichern</button>
</form>

<h2>Groupalarm API-Token</h2>
<form class="card" method="post" action="settings.php">
    <?php csrf_field(); ?>
    <input type="hidden" name="action" value="update_token">

    <p>
        Status:
        <?php if ($hasToken): ?>
            <strong>Konfiguriert</strong> (zuletzt geändert <?= h((string) $tokenUpdatedAt) ?>)
        <?php else: ?>
            <strong>Nicht konfiguriert</strong>
        <?php endif; ?>
    </p>

    <label for="api_token">Neuen Personal-Access-Token setzen</label>
    <input type="password" id="api_token" name="api_token" autocomplete="off" placeholder="Wird verschlüsselt gespeichert">
    <p class="field-hint">Der bestehende Token wird nie wieder angezeigt - nur ersetzbar.</p>

    <button type="submit">Token speichern</button>
</form>
<?php
require __DIR__ . '/../templates/footer.php';
