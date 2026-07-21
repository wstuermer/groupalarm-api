-- Adds the per-user default reminder setting (Einstellungen -> Standard-Erinnerung).
-- Required before deploying the corresponding app code - it reads/writes this column.
-- Safe to run against an already-populated production database: ADD COLUMN ... DEFAULT
-- backfills every existing row with 2880 (the value that was hardcoded before this
-- change), so behaviour for existing users is unchanged until they explicitly pick a
-- different default under Einstellungen.
--
-- Apply once with e.g.:
--   mysql -u root -p groupalarm_api < db/migrations/2026-07-21_add_default_reminder_minutes.sql

ALTER TABLE groupalarm_settings
    ADD COLUMN default_reminder_minutes SMALLINT UNSIGNED NULL DEFAULT 2880 AFTER organization_id;
