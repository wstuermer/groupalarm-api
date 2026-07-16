<?php

declare(strict_types=1);

/**
 * Sends the "forgot password" / "account invite" email containing a reset link.
 * Thin wrapper around mail() - swap the body of this function for an SMTP/PHPMailer
 * call later if mail() proves unreliable on the target host.
 */
function mail_send_reset_link(string $toEmail, string $rawToken, string $type): bool
{
    $link = APP_BASE_URL . '/reset_password.php?token=' . urlencode($rawToken);

    if ($type === 'invite') {
        $subject = 'Zugang zur Groupalarm-Terminverwaltung';
        $body = "Hallo,\n\n"
            . "für dich wurde ein Zugang zur Groupalarm-Terminverwaltung angelegt.\n"
            . "Bitte vergib über den folgenden Link (48 Stunden gültig) dein Passwort:\n\n"
            . $link . "\n\n"
            . "Die Anleitung findest du unter https://github.com/wstuermer/groupalarm-api/blob/main/docs/ANLEITUNG.md\n\n"
            . "Falls du diesen Zugang nicht erwartet hast, ignoriere diese Mail einfach.\n";
    } else {
        $subject = 'Passwort zurücksetzen';
        $body = "Hallo,\n\n"
            . "für deinen Account wurde ein Passwort-Reset angefordert.\n"
            . "Der folgende Link ist " . PASSWORD_RESET_TTL_MINUTES . " Minuten gültig:\n\n"
            . $link . "\n\n"
            . "Falls du das nicht warst, ignoriere diese Mail - es passiert nichts weiter.\n";
    }

    $headers = 'From: ' . APP_MAIL_FROM . "\r\n" . 'Content-Type: text/plain; charset=UTF-8';

    return mail($toEmail, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}
