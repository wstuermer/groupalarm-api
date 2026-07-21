-- Groupalarm Appointment Manager - schema
-- Import once against an empty database, e.g.:
--   mysql -u root -p groupalarm_api < db/schema.sql
--
-- Already have a populated database from before? Apply the scripts under
-- db/migrations/ instead (in filename order) - this file always reflects the
-- current full schema for fresh installs only, it is not itself a migration.

CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('admin','user') NOT NULL DEFAULT 'user',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at   DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 'reset' (10 min, self-service "forgot password") and 'invite' (48h, admin-created
-- accounts) share this table - same validate/consume logic, different expiry/purpose.
CREATE TABLE password_resets (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    token_hash    CHAR(64) NOT NULL,
    type          ENUM('reset','invite') NOT NULL DEFAULT 'reset',
    expires_at    DATETIME NOT NULL,
    used_at       DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pwreset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_pwreset_token_hash (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1:1 with users, kept separate so the ciphertext columns are never accidentally
-- pulled into a generic user-listing query (e.g. admin_users.php).
CREATE TABLE groupalarm_settings (
    user_id                   INT UNSIGNED PRIMARY KEY,
    organization_id           BIGINT UNSIGNED NULL,
    api_token_ciphertext      VARBINARY(1024) NULL,
    api_token_nonce           VARBINARY(16) NULL,
    api_token_tag             VARBINARY(16) NULL,
    -- Default "reminder" (minutes before an appointment) for newly created appointments,
    -- NULL means "keine Erinnerung". See inc/validation.php's REMINDER_OPTIONS for the
    -- allowed preset values.
    default_reminder_minutes SMALLINT UNSIGNED NULL DEFAULT 2880,
    updated_at                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_gasettings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_labels (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    label_id   BIGINT UNSIGNED NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_userlabel_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Immutable send log: one row per appointment actually POSTed to Groupalarm (success or error).
CREATE TABLE appointment_log (
    id                        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                   INT UNSIGNED NOT NULL,
    groupalarm_appointment_id BIGINT UNSIGNED NULL,
    name                      VARCHAR(255) NOT NULL,
    description               TEXT NOT NULL,
    start_date_local          DATETIME NOT NULL,
    end_date_local            DATETIME NOT NULL,
    organization_id           BIGINT UNSIGNED NOT NULL,
    label_ids_json            VARCHAR(500) NOT NULL,
    status                    ENUM('success','error') NOT NULL,
    http_status               SMALLINT UNSIGNED NULL,
    error_message             TEXT NULL,
    request_payload_json      TEXT NOT NULL,
    created_at                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_apptlog_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_apptlog_user_created (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
