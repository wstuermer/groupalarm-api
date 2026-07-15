<?php

declare(strict_types=1);

require __DIR__ . '/../inc/bootstrap.php';
require_admin();

$currentUserId = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_user') {
        $email = trim((string) ($_POST['email'] ?? ''));
        $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', 'Bitte eine gültige E-Mail-Adresse angeben.');
        } else {
            $exists = db()->prepare('SELECT id FROM users WHERE email = ?');
            $exists->execute([$email]);

            if ($exists->fetch()) {
                flash_set('error', 'Diese E-Mail-Adresse ist bereits registriert.');
            } else {
                // Unusable password: a hash of a random value nobody knows. The
                // account only becomes usable once the invite link is completed.
                $unusableHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
                db()->prepare('INSERT INTO users (email, password_hash, role, is_active) VALUES (?, ?, ?, 1)')
                    ->execute([$email, $unusableHash, $role]);
                $newUserId = (int) db()->lastInsertId();

                $rawToken = create_password_reset_token($newUserId, 'invite');
                mail_send_reset_link($email, $rawToken, 'invite');

                flash_set('success', "Benutzer {$email} angelegt, Einladungs-Mail verschickt.");
            }
        }
    } elseif (in_array($action, ['toggle_active', 'toggle_role', 'delete_user'], true)) {
        $targetId = (int) ($_POST['user_id'] ?? 0);

        if ($targetId === $currentUserId) {
            flash_set('error', 'Du kannst deinen eigenen Account nicht ändern/löschen.');
        } else {
            $stmt = db()->prepare('SELECT id, role, is_active FROM users WHERE id = ?');
            $stmt->execute([$targetId]);
            $target = $stmt->fetch();

            if (!$target) {
                flash_set('error', 'Benutzer nicht gefunden.');
            } elseif ($action === 'toggle_active') {
                db()->prepare('UPDATE users SET is_active = ? WHERE id = ?')
                    ->execute([(int) $target['is_active'] === 1 ? 0 : 1, $targetId]);
                flash_set('success', 'Status geändert.');
            } elseif ($action === 'toggle_role') {
                $newRole = $target['role'] === 'admin' ? 'user' : 'admin';
                db()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$newRole, $targetId]);
                flash_set('success', 'Rolle geändert.');
            } elseif ($action === 'delete_user') {
                db()->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);
                flash_set('success', 'Benutzer gelöscht.');
            }
        }
    }

    header('Location: admin_users.php');
    exit;
}

$users = db()->query(
    'SELECT id, email, role, is_active, created_at, last_login_at FROM users ORDER BY email'
)->fetchAll();

$title = 'Benutzerverwaltung';
require __DIR__ . '/../templates/header.php';
?>
<h1>Benutzerverwaltung</h1>

<h2>Neuen Benutzer anlegen</h2>
<form class="card" method="post" action="admin_users.php">
    <?php csrf_field(); ?>
    <input type="hidden" name="action" value="create_user">

    <label for="email">E-Mail</label>
    <input type="email" id="email" name="email" required>

    <label for="role">Rolle</label>
    <select id="role" name="role">
        <option value="user">User</option>
        <option value="admin">Admin</option>
    </select>
    <p class="field-hint">Der Benutzer erhält eine Mail mit einem 48h gültigen Link zur Passwortvergabe.</p>

    <button type="submit">Benutzer anlegen</button>
</form>

<h2>Bestehende Benutzer</h2>
<table>
    <thead>
        <tr><th>E-Mail</th><th>Rolle</th><th>Status</th><th>Letzter Login</th><th>Aktionen</th></tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <tr>
            <td><?= h($u['email']) ?></td>
            <td><span class="badge badge-<?= h($u['role']) ?>"><?= h($u['role']) ?></span></td>
            <td>
                <?php if ((int) $u['is_active'] === 1): ?>
                    aktiv
                <?php else: ?>
                    <span class="badge badge-inactive">deaktiviert</span>
                <?php endif; ?>
            </td>
            <td><?= h($u['last_login_at'] ?? '-') ?></td>
            <td class="actions-row">
                <?php if ((int) $u['id'] === $currentUserId): ?>
                    (du)
                <?php else: ?>
                    <form method="post" action="admin_users.php">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="toggle_role">
                        <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                        <button type="submit" class="btn-secondary"><?= $u['role'] === 'admin' ? 'Zu User machen' : 'Zu Admin machen' ?></button>
                    </form>
                    <form method="post" action="admin_users.php">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="toggle_active">
                        <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                        <button type="submit" class="btn-secondary"><?= (int) $u['is_active'] === 1 ? 'Deaktivieren' : 'Aktivieren' ?></button>
                    </form>
                    <form method="post" action="admin_users.php" data-confirm="Benutzer <?= h($u['email']) ?> wirklich löschen?">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                        <button type="submit" class="btn-danger">Löschen</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php
require __DIR__ . '/../templates/footer.php';
