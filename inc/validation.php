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

    // Expand literal "\n" the same way the API client will, so an all-whitespace
    // description (e.g. just "\n") is correctly caught as empty.
    $description = str_replace('\\n', "\n", (string) ($row['description'] ?? ''));
    if (trim($description) === '') {
        $errors[] = 'Beschreibung fehlt.';
    }

    return $errors;
}

/**
 * Validates that the given user has enough Groupalarm configuration (org ID + at
 * least one label) to actually send appointments. Checked once per batch send, not
 * per row, since these are user-level settings.
 */
function validate_user_groupalarm_config(?int $organizationId, array $labelIds, ?string $apiToken): array
{
    $errors = [];

    if ($organizationId === null || $organizationId <= 0) {
        $errors[] = 'Keine Organisation-ID hinterlegt (siehe Einstellungen).';
    }
    if (empty($labelIds)) {
        $errors[] = 'Keine Label-IDs hinterlegt (siehe Einstellungen).';
    }
    if ($apiToken === null || $apiToken === '') {
        $errors[] = 'Kein Groupalarm-API-Token hinterlegt (siehe Einstellungen).';
    }

    return $errors;
}
