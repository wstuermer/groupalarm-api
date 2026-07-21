<?php

declare(strict_types=1);

/**
 * Returns true if $date is a syntactically and calendrically valid YYYY-MM-DD date.
 */
function is_valid_date(string $date): bool
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
        return false;
    }
    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}

/**
 * Returns true if $time is a syntactically valid 24h HH:MM value.
 */
function is_valid_time(string $time): bool
{
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time)) {
        return false;
    }
    return true;
}

/**
 * Normalizes a description as entered by the user: expands the literal two-character
 * "\n" escape sequence (the appointments.txt convention) into a real newline, and
 * collapses CRLF/lone-CR line endings down to plain LF. Browsers submit <textarea>
 * content with CRLF line breaks per the HTML spec regardless of OS, so a description
 * typed into add_appointment.php's/draft_edit.php's textarea would otherwise carry
 * stray \r bytes into the Groupalarm payload. Used consistently for validation and
 * for the payload actually sent to Groupalarm, so both agree on the same text.
 */
function normalize_description(string $raw): string
{
    $text = str_replace('\\n', "\n", $raw);
    return preg_replace('/\r\n|\r/', "\n", $text);
}

/**
 * Validates one draft row (as built by the manual-entry form, the txt parser, or an
 * inline edit) and returns a list of human-readable error strings. Empty array = valid.
 * Called from every place a row is created/edited, and again defensively right before
 * a batch send (a label/org setting referenced earlier may have changed since).
 */
function validate_draft_row(array $row): array
{
    $errors = [];

    $date = trim((string) ($row['date'] ?? ''));
    if ($date === '') {
        $errors[] = 'Datum fehlt.';
    } elseif (!is_valid_date($date)) {
        $errors[] = 'Ungültiges Datum.';
    }

    $start = trim((string) ($row['start_time'] ?? ''));
    $end = trim((string) ($row['end_time'] ?? ''));
    if (!is_valid_time($start)) {
        $errors[] = 'Ungültige Startzeit.';
    }
    if (!is_valid_time($end)) {
        $errors[] = 'Ungültige Endzeit.';
    }
    if (is_valid_time($start) && is_valid_time($end) && $start >= $end) {
        $errors[] = 'Endzeit muss nach der Startzeit liegen.';
    }

    $name = trim((string) ($row['name'] ?? ''));
    if ($name === '') {
        $errors[] = 'Betreff fehlt.';
    }

    $description = normalize_description((string) ($row['description'] ?? ''));
    if (trim($description) === '') {
        $errors[] = 'Beschreibung fehlt.';
    }

    if (empty($row['label_ids'] ?? [])) {
        $errors[] = 'Mindestens ein Label muss ausgewählt sein.';
    }

    return $errors;
}

/**
 * Validates that the given user has enough Groupalarm configuration (org ID + API
 * token) to actually send appointments. Checked once per batch send, not per row,
 * since these are user-level settings. Labels are validated per row instead (see
 * validate_draft_row()), since each appointment now carries its own label_ids.
 */
function validate_user_groupalarm_config(?int $organizationId, ?string $apiToken): array
{
    $errors = [];

    if ($organizationId === null || $organizationId <= 0) {
        $errors[] = 'Keine Organisation-ID hinterlegt (siehe Einstellungen).';
    }
    if ($apiToken === null || $apiToken === '') {
        $errors[] = 'Kein Groupalarm-API-Token hinterlegt (siehe Einstellungen).';
    }

    return $errors;
}
