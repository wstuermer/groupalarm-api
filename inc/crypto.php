<?php

declare(strict_types=1);

/**
 * Loads the 32-byte AES-256-GCM master key from GROUPALARM_MASTER_KEY_PATH,
 * generating it on first use if the file doesn't exist yet.
 */
function groupalarm_master_key(): string
{
    $path = GROUPALARM_MASTER_KEY_PATH;

    if (!is_file($path)) {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create directory for master key: {$dir}");
        }
        $key = random_bytes(32);
        if (file_put_contents($path, $key, LOCK_EX) === false) {
            throw new RuntimeException("Cannot write master key file: {$path}");
        }
        chmod($path, 0600);
        return $key;
    }

    $key = file_get_contents($path);
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException("Master key file is missing or invalid: {$path}");
    }

    return $key;
}

/**
 * Encrypts a Groupalarm Personal-Access-Token for storage.
 * Returns ['ciphertext' => string, 'nonce' => string, 'tag' => string] (all raw binary).
 */
function groupalarm_encrypt_token(string $plainToken): array
{
    $key = groupalarm_master_key();
    $nonce = random_bytes(12);
    $tag = '';

    $ciphertext = openssl_encrypt($plainToken, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
    if ($ciphertext === false) {
        throw new RuntimeException('Failed to encrypt Groupalarm API token.');
    }

    return ['ciphertext' => $ciphertext, 'nonce' => $nonce, 'tag' => $tag];
}

/**
 * Decrypts a Groupalarm Personal-Access-Token previously stored via groupalarm_encrypt_token().
 * Returns null (not false) if decryption/auth fails, so callers can't accidentally treat
 * a falsy-but-truthy-looking value as a usable token.
 */
function groupalarm_decrypt_token(string $ciphertext, string $nonce, string $tag): ?string
{
    if ($ciphertext === '' || $nonce === '' || $tag === '') {
        return null;
    }

    $key = groupalarm_master_key();
    $plain = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);

    return $plain === false ? null : $plain;
}
