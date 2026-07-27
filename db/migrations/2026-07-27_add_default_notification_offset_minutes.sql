-- Adds the per-user default "Versandzeitpunkt" setting (Einstellungen -> wann die
-- Termin-Einladung verschickt wird, statt sofort bei Erstellung). Required before
-- deploying the corresponding app code - it reads/writes this column.
-- Safe to run against an already-populated production database: the new column
-- defaults to NULL ("nicht gesetzt"), i.e. invitations keep going out immediately at
-- creation for every existing user until they explicitly pick a different default
-- under Einstellungen - no change in behaviour for anyone until they opt in.
--
-- Apply once with e.g.:
--   mysql -u root -p groupalarm_api < db/migrations/2026-07-27_add_default_notification_offset_minutes.sql

ALTER TABLE groupalarm_settings
    ADD COLUMN default_notification_offset_minutes SMALLINT UNSIGNED NULL DEFAULT NULL AFTER default_reminder_minutes;
