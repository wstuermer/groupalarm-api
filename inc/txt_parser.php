<?php

declare(strict_types=1);

/**
 * Parses an appointments.txt-shaped string into draft rows.
 *
 * Grammar (backward-compatible superset of the original automata.sh format):
 *   YYYY-MM-DD[ HH:MM-HH:MM] Description
 *
 * - Date is required and must be a real calendar date.
 * - An optional "HH:MM-HH:MM " time range may follow the date; if present, everything
 *   after it is the description. If absent, DEFAULT_APPOINTMENT_START_TIME/END_TIME
 *   apply and everything after the date is the description.
 * - A malformed-looking time-range token (e.g. "25:00-19:00") is treated as an error,
 *   not silently replaced by the defaults - the intent to specify a custom time was
 *   clear, so a typo there should be flagged rather than swallowed.
 * - Lines starting with '#' (after trimming) and blank lines are ignored.
 * - Literal two-character "\n" sequences in the description are left as-is here and
 *   expanded to real newlines later by groupalarm_client.php / validate_draft_row(),
 *   matching automata.sh's existing behaviour.
 *
 * Returns a list of draft rows (see make_draft_row()); invalid lines are included with
 * their errors populated, not dropped, so the review screen can show and fix them.
 *
 * $defaultLabelIds/$defaultReminderMinutes are stamped onto every parsed row (the
 * user's current Groupalarm defaults from Einstellungen) so uploaded rows start
 * with sensible labels/reminder, editable afterwards per row in the review view.
 */
function parse_appointments_text(string $text, array $defaultLabelIds = [], ?int $defaultReminderMinutes = DEFAULT_APPOINTMENT_REMINDER_MINUTES): array
{
    $rows = [];
    $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];

    foreach ($lines as $index => $rawLine) {
        $lineNumber = $index + 1;
        $line = trim($rawLine);

        if ($line === '' || $line[0] === '#') {
            continue;
        }

        if (!mb_check_encoding($line, 'UTF-8')) {
            $rows[] = make_draft_row(
                ['description' => $rawLine, 'label_ids' => $defaultLabelIds, 'reminder_minutes' => $defaultReminderMinutes],
                'upload',
                ["Zeile {$lineNumber}: ungültige Zeichenkodierung (bitte UTF-8)."],
                $lineNumber
            );
            continue;
        }

        if (!preg_match('/^(\d{4}-\d{2}-\d{2})\s+(.*)$/', $line, $m)) {
            $rows[] = make_draft_row(
                ['description' => $line, 'label_ids' => $defaultLabelIds, 'reminder_minutes' => $defaultReminderMinutes],
                'upload',
                ["Zeile {$lineNumber}: erwarte 'YYYY-MM-DD Beschreibung'."],
                $lineNumber
            );
            continue;
        }

        $date = $m[1];
        $rest = $m[2];

        $startTime = DEFAULT_APPOINTMENT_START_TIME;
        $endTime = DEFAULT_APPOINTMENT_END_TIME;
        $description = $rest;
        $extraErrors = [];

        // Loosely shaped match first (1-2 digit hour) so an obviously-attempted but
        // malformed time range (e.g. "25:00-19:00") is caught below as an error,
        // rather than silently falling through to the default times.
        if (preg_match('/^(\d{1,2}:\d{2})-(\d{1,2}:\d{2})\s+(.*)$/', $rest, $tm)) {
            $startTime = $tm[1];
            $endTime = $tm[2];
            $description = $tm[3];

            if (!is_valid_time($startTime)) {
                $extraErrors[] = "Zeile {$lineNumber}: ungültige Startzeit '{$startTime}'.";
            }
            if (!is_valid_time($endTime)) {
                $extraErrors[] = "Zeile {$lineNumber}: ungültige Endzeit '{$endTime}'.";
            }
        }

        $rows[] = make_draft_row(
            [
                'date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'name' => DEFAULT_APPOINTMENT_NAME,
                'description' => $description,
                'label_ids' => $defaultLabelIds,
                'reminder_minutes' => $defaultReminderMinutes,
            ],
            'upload',
            $extraErrors,
            $lineNumber
        );
    }

    return $rows;
}
