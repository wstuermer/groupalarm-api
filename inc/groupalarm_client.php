<?php

declare(strict_types=1);

const GROUPALARM_API_URL = 'https://app.groupalarm.com/api/v1/appointment';

/**
 * Raw groupalarm_settings row for a user (organization_id + encrypted token columns),
 * or null if the user has never saved any settings yet. Does NOT decrypt the token -
 * use groupalarm_get_decrypted_token() only at the point the raw value is actually needed.
 */
function groupalarm_get_settings_row(int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM groupalarm_settings WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

function groupalarm_get_label_ids(int $userId): array
{
    $stmt = db()->prepare('SELECT label_id FROM user_labels WHERE user_id = ? ORDER BY sort_order, id');
    $stmt->execute([$userId]);
    return array_map('intval', array_column($stmt->fetchAll(), 'label_id'));
}

/**
 * Decrypts and returns the user's Groupalarm Personal-Access-Token, or null if none
 * is stored / decryption fails. Only call this right before an actual API send.
 */
function groupalarm_get_decrypted_token(int $userId): ?string
{
    $row = groupalarm_get_settings_row($userId);
    if ($row === null || $row['api_token_ciphertext'] === null) {
        return null;
    }
    return groupalarm_decrypt_token($row['api_token_ciphertext'], $row['api_token_nonce'], $row['api_token_tag']);
}

/**
 * Converts a Europe/Berlin wall-clock date/time (as entered by the user) into the
 * real UTC timestamp Groupalarm expects. Confirmed against a live test: sending the
 * literal local time with a bare "Z" suffix (assuming Groupalarm would treat it as
 * already-local, per automata.sh's apparent behaviour) resulted in appointments
 * showing up 2 hours later than requested - i.e. Groupalarm genuinely interprets the
 * "Z" as UTC and converts to Europe/Berlin for display using the payload's separate
 * "timezone" field. This performs that conversion properly, including DST (CET/CEST),
 * so the appointment shows at the intended local time regardless of time of year.
 */
function groupalarm_to_utc_timestamp(string $date, string $time): string
{
    $local = new DateTime("{$date} {$time}:00", new DateTimeZone('Europe/Berlin'));
    $local->setTimezone(new DateTimeZone('UTC'));

    return $local->format('Y-m-d\TH:i:s\Z');
}

/**
 * Builds the JSON-ready payload array for one appointment. Labels are per-row
 * (each draft row carries its own label_ids, editable in the review view) -
 * isPublic/keepLabelParticipantsInSync/reminder are fixed to sensible defaults -
 * not part of this app's scope, easy to expose as settings later if needed.
 */
function groupalarm_build_payload(array $row, int $organizationId): array
{
    return [
        'description' => normalize_description((string) $row['description']),
        'startDate' => groupalarm_to_utc_timestamp($row['date'], $row['start_time']),
        'endDate' => groupalarm_to_utc_timestamp($row['date'], $row['end_time']),
        'isPublic' => false,
        'keepLabelParticipantsInSync' => true,
        'labelIDs' => array_values(array_map('intval', $row['label_ids'] ?? [])),
        'participants' => [],
        'name' => $row['name'] !== '' ? $row['name'] : DEFAULT_APPOINTMENT_NAME,
        'organizationID' => $organizationId,
        'timezone' => 'Europe/Berlin',
        'reminder' => DEFAULT_APPOINTMENT_REMINDER_MINUTES,
    ];
}

/**
 * POSTs one appointment payload to Groupalarm. Never throws for HTTP/API-level
 * failures - always returns a result array so a batch send can continue past a
 * single failed row. Only truly unexpected errors (e.g. curl extension missing)
 * would throw, which callers should still guard with try/catch.
 */
function groupalarm_send_appointment(string $token, array $payload): array
{
    $ch = curl_init(GROUPALARM_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Personal-Access-Token: ' . $token,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);

    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlErrno !== 0) {
        return ['success' => false, 'http_status' => null, 'error' => $curlError, 'response' => null];
    }

    $decoded = json_decode((string) $body, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'http_status' => $httpCode, 'error' => null, 'response' => $decoded];
    }

    $errMsg = is_array($decoded) ? ($decoded['message'] ?? $decoded['error'] ?? ('HTTP ' . $httpCode)) : ('HTTP ' . $httpCode);

    return ['success' => false, 'http_status' => $httpCode, 'error' => (string) $errMsg, 'response' => $decoded];
}

/**
 * Sends every given draft row (already filtered to error-free rows by the caller) as
 * one appointment each. Returns results keyed by row_id, each entry additionally
 * carrying the payload and original row for logging. One row's failure never aborts
 * the remaining rows.
 */
function groupalarm_send_batch(string $token, int $organizationId, array $rows): array
{
    $results = [];

    foreach ($rows as $row) {
        $payload = groupalarm_build_payload($row, $organizationId);

        try {
            $result = groupalarm_send_appointment($token, $payload);
        } catch (Throwable $e) {
            $result = ['success' => false, 'http_status' => null, 'error' => $e->getMessage(), 'response' => null];
        }

        $results[$row['row_id']] = $result + ['payload' => $payload, 'row' => $row];
    }

    return $results;
}
