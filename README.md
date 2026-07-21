# Groupalarm-Terminverwaltung

Eine schlanke, abhängigkeitsfreie PHP-Web-Anwendung, mit der (nicht-technische)
Nutzer:innen Termine (z.B. Übungsdienste) vorbereiten, prüfen und gebündelt an
[Groupalarm](https://www.groupalarm.com/) senden können - ohne dass sie selbst
Zugriff auf einen vollwertigen Groupalarm-Account oder dessen API benötigen.

Termine landen zunächst in einer Entwurfsliste, werden dort validiert und lassen
sich vor dem eigentlichen Versand noch einzeln korrigieren. Nichts wird ungeprüft
sofort an Groupalarm übermittelt.

## Features

- **Termine anlegen** - einzeln per Formular oder gebündelt per Textdatei-Upload
  (`YYYY-MM-DD[ HH:MM-HH:MM] Beschreibung`, eine Zeile pro Termin)
- **Entwurfsliste als Kontrollansicht** - alle vorbereiteten Termine inkl. Labels an
  einem Ort, fehlerhafte Zeilen (ungültiges Datum, fehlendes Label o.ä.) sind
  markiert und lassen sich vor dem Senden korrigieren oder löschen
- **Groupalarm-Labels direkt auswählbar** - Labels werden live aus der eigenen
  Groupalarm-Organisation geladen und per Mehrfachauswahl zugewiesen; ein
  Standard-Set aus den Einstellungen wird neuen Terminen automatisch vorbelegt,
  ist aber pro Termin überschreibbar
- **Batch-Versand** - alle fehlerfreien Entwürfe mit einem Klick an die
  Groupalarm-API senden, inklusive Sende-Protokoll (`appointment_log`) pro Termin
- **Benutzerverwaltung** - Admins legen Accounts per E-Mail-Einladung an
  (`admin_users.php`), Zugangsdaten (Organisation-ID, Personal-Access-Token,
  Standard-Labels) sind pro Benutzer getrennt hinterlegt
- **Sicherheit** - Passwort-Hashing, CSRF-Schutz auf allen Formularen, gehärtete
  Sessions, und Groupalarm-API-Tokens werden AES-256-GCM-verschlüsselt in der
  Datenbank abgelegt (nie im Klartext gespeichert oder erneut angezeigt)

## Technischer Überblick

Bewusst einfach gehalten - kein Framework, kein Build-Step, keine JS-Abhängigkeiten:

- **Sprache/Runtime:** PHP (8.1+), reines `PDO` für die Datenbank
- **Datenbank:** MySQL/MariaDB (Schema in [`db/schema.sql`](db/schema.sql))
- **Frontend:** serverseitig gerendertes PHP + eine einzelne CSS-Datei
  (`public/assets/style.css`); jedes Formular funktioniert auch ohne JavaScript
- **Externe API:** [Groupalarm Alarming API](https://developer.groupalarm.com/api/alarming.html)
  (`inc/groupalarm_client.php` zum Anlegen von Terminen, `inc/groupalarm_labels.php`
  zum Laden der Labels)

### Projektstruktur

```
public/     Aufrufbare Einstiegspunkte (Document Root des Webservers)
inc/        Geteilte PHP-Logik (Auth, Validierung, DB, Groupalarm-Client, ...)
templates/  Kleine, wiederverwendete HTML-Partials (Header/Footer/Flash-Messages)
db/         Datenbankschema (schema.sql)
docs/       Anwender-Anleitung inkl. Screenshots (ANLEITUNG.md)
secrets/    Verschlüsselungs-Schlüssel (nicht Teil des Repos, siehe unten)
logs/       PHP-Error-Log der Anwendung
```

## Voraussetzungen

- PHP 8.1 oder neuer mit den Erweiterungen `pdo_mysql` und `curl`
- MySQL oder MariaDB
- Ein funktionierender Mailversand auf dem Server (`mail()`), für
  Passwort-Reset- und Einladungs-Mails
- Ein Groupalarm-Account je Nutzer:in mit Personal-Access-Token
  (siehe [`docs/ANLEITUNG.md`](docs/ANLEITUNG.md), Abschnitt 2)

## Installation

1. **Repository auf den Server bringen** und den Webserver (Apache/Nginx) so
   konfigurieren, dass das **Document Root direkt auf `public/`** zeigt - nicht
   auf das Repository-Wurzelverzeichnis. Alles außerhalb von `public/` (Config,
   Secrets, Logs) ist damit vom Web aus nicht erreichbar.

2. **Datenbank anlegen** und Schema importieren:
   ```bash
   mysql -u root -p groupalarm_api < db/schema.sql
   ```

3. **Konfiguration anlegen:**
   ```bash
   cp config.php.example config.php
   ```
   und darin Datenbankzugang, `APP_BASE_URL`, Absenderadresse für Mails sowie bei
   Bedarf die Termin-Standardwerte anpassen. `config.php` ist in `.gitignore` und
   darf nie eingecheckt werden.

4. **Master-Key für die Token-Verschlüsselung erzeugen** (liegt außerhalb von
   `public/`, Zugriffsrechte `600`):
   ```bash
   php -r "file_put_contents('secrets/master.key', random_bytes(32));"
   chmod 600 secrets/master.key
   ```
   Geht dieser Schlüssel verloren, sind alle gespeicherten Groupalarm-Tokens
   unwiederbringlich - Nutzer:innen müssten ihren Token dann einmalig neu
   hinterlegen.

5. **Schreibrechte prüfen:** Der Webserver-User braucht Schreibzugriff auf
   `logs/` (PHP-Error-Log).

6. **Aufrufen:** Beim ersten Besuch der Anwendung ohne existierende Benutzer wird
   automatisch auf die Einrichtung des ersten Admin-Accounts weitergeleitet
   (`setup_admin.php`). Weitere Accounts legt dieser Admin anschließend unter
   "Benutzer" per E-Mail-Einladung an.

## Nutzung

Eine ausführliche, für Endanwender:innen geschriebene Anleitung (Zugang erhalten,
Groupalarm-Zugangsdaten hinterlegen, Termine anlegen und senden) liegt unter
[`docs/ANLEITUNG.md`](docs/ANLEITUNG.md).

## Sicherheitshinweise

- Personal-Access-Tokens werden nie im Klartext gespeichert oder erneut angezeigt
  (nur "konfiguriert"/"nicht konfiguriert").
- Sessions sind mit `httponly`-, `samesite=Lax`- und (bei HTTPS) `secure`-Cookies
  sowie einem Idle-Timeout gehärtet.
- Alle POST-Formulare sind per CSRF-Token geschützt.
- `config.php`, `secrets/` und `logs/*.log` sind über `.gitignore` vom Repository
  ausgeschlossen - beim Deployment darauf achten, dass diese Pfade auch serverseitig
  nicht öffentlich erreichbar sind.
