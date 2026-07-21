<?php

declare(strict_types=1);

const GROUPALARM_LABELS_API_URL = 'https://app.groupalarm.com/api/v1/labels';
const GROUPALARM_LABELS_CACHE_TTL_SECONDS = 300;

/**
 * Fetches all (non-smart) labels of the given organization from Groupalarm.
 * Follows the same never-throw-for-HTTP/API-failures convention as
 * groupalarm_send_appointment() - always returns a structured result array.
 */
function groupalarm_fetch_labels(string $token, int $organizationId): array
{
    $url = GROUPALARM_LABELS_API_URL . '?' . http_build_query([
        'organization' => $organizationId,
        'all' => 'true',
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Personal-Access-Token: ' . $token,
        ],
    ]);

    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlErrno !== 0) {
        return ['success' => false, 'error' => $curlError, 'labels' => []];
    }

    $decoded = json_decode((string) $body, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        $errMsg = is_array($decoded) ? ($decoded['message'] ?? $decoded['error'] ?? ('HTTP ' . $httpCode)) : ('HTTP ' . $httpCode);
        return ['success' => false, 'error' => (string) $errMsg, 'labels' => []];
    }

    // The API is expected to return a flat array of label objects; tolerate a
    // {"labels": [...]} wrapper too in case the response shape differs at runtime.
    if (is_array($decoded) && array_is_list($decoded)) {
        $items = $decoded;
    } else {
        $items = is_array($decoded) ? (array) ($decoded['labels'] ?? []) : [];
    }

    $labels = [];
    foreach ($items as $item) {
        if (!is_array($item) || !isset($item['id'], $item['name'])) {
            continue;
        }
        $labels[] = ['id' => (int) $item['id'], 'name' => (string) $item['name']];
    }
    usort($labels, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

    return ['success' => true, 'error' => null, 'labels' => $labels];
}

/**
 * User-facing wrapper around groupalarm_fetch_labels(): resolves the user's
 * organization id + token, and applies a short session-lived cache (keyed by
 * organization id) so pages that render a label picker on every request don't
 * hit Groupalarm on every single page view. On a failed live fetch, a previous
 * (even expired) cached result is served instead of an empty list - a stale
 * label list is preferable to breaking the picker over a transient blip.
 */
function groupalarm_get_labels_for_user(int $userId): array
{
    $settingsRow = groupalarm_get_settings_row($userId);
    $organizationId = $settingsRow['organization_id'] ?? null;
    $token = groupalarm_get_decrypted_token($userId);

    if ($organizationId === null) {
        return ['success' => false, 'error' => 'Keine Organisation-ID hinterlegt (siehe Einstellungen).', 'labels' => []];
    }
    if ($token === null || $token === '') {
        return ['success' => false, 'error' => 'Kein Groupalarm-API-Token hinterlegt (siehe Einstellungen).', 'labels' => []];
    }

    $organizationId = (int) $organizationId;
    $cached = $_SESSION['groupalarm_labels_cache'][$organizationId] ?? null;

    if ($cached !== null && (time() - $cached['fetched_at']) < GROUPALARM_LABELS_CACHE_TTL_SECONDS) {
        return ['success' => true, 'error' => null, 'labels' => $cached['labels']];
    }

    $result = groupalarm_fetch_labels($token, $organizationId);

    if ($result['success']) {
        $_SESSION['groupalarm_labels_cache'][$organizationId] = [
            'fetched_at' => time(),
            'labels' => $result['labels'],
        ];
        return $result;
    }

    if ($cached !== null) {
        return ['success' => false, 'error' => $result['error'], 'labels' => $cached['labels']];
    }

    return $result;
}

/**
 * Builds an id => name lookup map from a groupalarm_get_labels_for_user() result,
 * used to resolve label ids to display names (e.g. dashboard.php's label column).
 */
function groupalarm_label_name_map(array $labels): array
{
    return array_column($labels, 'name', 'id');
}
