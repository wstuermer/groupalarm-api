<?php

declare(strict_types=1);

/**
 * Builds a fully-shaped draft row (with a fresh row_id and validation errors already
 * computed) from a partial set of fields. Used by add_appointment.php (manual entry),
 * txt_parser.php (one call per parsed line), and draft_edit.php (after an inline edit).
 *
 * $extraErrors are prepended (e.g. parser-level errors like an unparseable time range)
 * on top of whatever validate_draft_row() finds.
 */
function make_draft_row(array $fields, string $source, array $extraErrors = [], ?int $lineNumber = null): array
{
    $row = [
        'row_id' => bin2hex(random_bytes(8)),
        'date' => $fields['date'] ?? '',
        'start_time' => $fields['start_time'] ?? DEFAULT_APPOINTMENT_START_TIME,
        'end_time' => $fields['end_time'] ?? DEFAULT_APPOINTMENT_END_TIME,
        'name' => $fields['name'] ?? DEFAULT_APPOINTMENT_NAME,
        'description' => $fields['description'] ?? '',
        'source' => $source,
        'line_number' => $lineNumber,
    ];

    $row['errors'] = array_merge($extraErrors, validate_draft_row($row));

    return $row;
}

function draft_get_all(): array
{
    return $_SESSION['draft'] ?? [];
}

function draft_add_row(array $row): void
{
    $_SESSION['draft'][] = $row;
}

function draft_add_rows(array $rows): void
{
    foreach ($rows as $row) {
        draft_add_row($row);
    }
}

function draft_find_index(string $rowId): ?int
{
    foreach (draft_get_all() as $index => $row) {
        if ($row['row_id'] === $rowId) {
            return $index;
        }
    }
    return null;
}

/**
 * Merges $fields into the existing row and re-validates it - unless $fields itself
 * explicitly sets 'errors' (used by dashboard.php to attach a Groupalarm API failure
 * message after a send attempt), in which case that takes precedence instead of being
 * silently recomputed away: the row's fields are known-valid at that point (only
 * already-validated rows get sent), so the only error left to show is the API's.
 */
function draft_update_row(string $rowId, array $fields): bool
{
    $index = draft_find_index($rowId);
    if ($index === null) {
        return false;
    }

    $row = array_merge($_SESSION['draft'][$index], $fields);
    if (!array_key_exists('errors', $fields)) {
        $row['errors'] = validate_draft_row($row);
    }
    $_SESSION['draft'][$index] = $row;

    return true;
}

function draft_delete_row(string $rowId): bool
{
    $index = draft_find_index($rowId);
    if ($index === null) {
        return false;
    }
    array_splice($_SESSION['draft'], $index, 1);
    return true;
}

/**
 * Removes rows by row_id (used to drop successfully-sent rows after a batch send).
 */
function draft_remove_rows(array $rowIds): void
{
    $_SESSION['draft'] = array_values(array_filter(
        draft_get_all(),
        fn (array $row) => !in_array($row['row_id'], $rowIds, true)
    ));
}

function draft_clear(): void
{
    $_SESSION['draft'] = [];
}
