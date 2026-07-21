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
 * Plausible preset values for an appointment's "reminder" (minutes before the
 * appointment after which participants who haven't responded get a push reminder).
 * Bounded by Groupalarm's API limits (0-10080 minutes / 7 days, see
 * https://developer.groupalarm.com/api/appointment.html#tag/appointment/operation/CreateAppointment).
 * '' (empty string, normalizes to null) means "no reminder" - the reminder key is
 * then omitted from the payload entirely rather than sending e.g. 0.
 */
const REMINDER_OPTIONS = [
    '' => 'Keine Erinnerung',
    60 => '1 Stunde vorher',
    180 => '3 Stunden vorher',
    360 => '6 Stunden vorher',
    720 => '12 Stunden vorher',
    1440 => '1 Tag vorher',
    2880 => '2 Tage vorher (Standard)',
    4320 => '3 Tage vorher',
    7200 => '5 Tage vorher',
    10080 => '7 Tage vorher (Maximum)',
];

/**
 * Normalizes a raw reminder value (from $_POST, a stored default, or an explicit
 * null) against REMINDER_OPTIONS. Anything not exactly matching one of the preset
 * values (e.g. a tampered request) silently falls back to null ("keine Erinnerung")
 * rather than surfacing a row error - there's always a safe, valid value to fall
 * back to, unlike labels which have no safe default.
 */
function normalize_reminder_minutes(mixed $raw): ?int
{
    if ($raw === null || $raw === '') {
        return null;
    }
    $minutes = (int) $raw;
    return array_key_exists($minutes, REMINDER_OPTIONS) ? $minutes : null;
}

/**
 * Human-readable label for a normalized reminder value, e.g. for the dashboard's
 * reminder column. Falls back to a raw-minutes display for a value that somehow
 * isn't one of the presets (should not normally happen, normalize_reminder_minutes()
 * already guards against that on every write path).
 */
function reminder_option_label(?int $minutes): string
{
    return REMINDER_OPTIONS[$minutes ?? ''] ?? "{$minutes} Minuten vorher";
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
