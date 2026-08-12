# Changelog

Alle nennenswerten Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
die Versionierung folgt [Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

### Hinzugefügt
- Repository-Grundstruktur: Plugin-Hauptklasse, Verzeichnislayout, Lizenz, leere Sprachdateien.
- Konzeptdokumentation (`ROADMAP.md`, `KONZEPT-Bordmittel.md`) und Projekt-README.
- Lokale MantisBT-Testumgebung unter `docker/` (Docker Compose, MantisBT 2.28.3,
  MariaDB, Mailpit) zum Ausprobieren des Plugins.
- **F1.1 Schema-Migration:** sechs Tabellen (`qt_massnahme`, `qt_person`,
  `qt_profil`, `qt_profil_massnahme`, `qt_zuordnung`, `qt_veranstaltung`) über
  die Mantis-Plugin-Schema-API inkl. Indizes; Tabellen-Rückbau bei
  Deinstallation. Datenmodell setzt die Entscheidungen E1/E2/E3/E5/E6 um.
- **F1.2 Maßnahmenkatalog:** Verwaltung von Maßnahmen (Anlegen, Bearbeiten,
  Löschen) unter *Verwaltung → QualificationTracker* mit Zugriffsprüfung,
  CSRF-Schutz und Ausgabe-Escaping. Datenschicht `core/QT_Catalog.php` mit reiner,
  unit-getesteter Validierung; PHPUnit-Grundgerüst (`phpunit.xml.dist`, `tests/`).
- **F1.3 Vorbedingungen:** Eine Maßnahme kann andere als Voraussetzung
  referenzieren (Qualifikation → Beauftragung → Unterweisung). Neue Tabelle
  `qt_massnahme_vorbedingung`; `core/QT_Prerequisite.php` mit reiner,
  unit-getesteter Zyklenerkennung (Selbstreferenzen und Kreise werden beim
  Speichern abgewiesen). Auswahl im Katalog-Formular, Anzeige in der Liste.
- **F1.4 Personenregister:** Verwaltung von Personen unabhängig von
  Mantis-Benutzerkonten (Personalnummer, Name, Typ, Abteilung, Ein-/Austritt,
  Vorgesetzter, Jugendschutz-Stichdatum) unter *Verwaltung → QualificationTracker
  → Personenregister*, mit Abteilungsfilter. `core/QT_Person.php` mit reiner,
  unit-getesteter Validierung. Setzt E1 (nullable-unique Personalnummer), E2
  (Typ-Diskriminator + Fremdfirma) und E5 (Jugendschutz-Stichdatum) um.
- **F1.8/F1.9 Fälligkeitsrechner:** `core/QT_DueDateCalculator.php` als einzige
  Stelle der Terminberechnung — reine, seiteneffektfreie Klasse mit den vier
  Modi (`rollierend`/`kalenderjahr`/`stichmonat`/`extern`), Monatsende-Klemmung,
  Schaltjahr-Behandlung, Ankererhalt gegen Vorwärtsdrift (Karenzfenster) und
  Erstzyklus-Berechnung. Per-Abteilung-`stichmonat` als Parameter unterstützt.
  Vollständig per PHPUnit abgedeckt (100 % Zeilen der Klasse; alle beschriebenen
  Randfälle inkl. rückdatierter Nacherfassung).
- **F1.6 Konfigurationsseite:** *Verwaltung → QualificationTracker → Konfiguration*
  (Administrator) für Verwaltungs-Zugriffsstufe, Fälligkeits-Vorgaben (Modus,
  Stichmonat, Karenz, Ersteinweisungsfrist), die per-Abteilung-`stichmonat`-
  Staffelung, die Eskalationstage und das Zielprojekt. Neue Konfig-Vorgaben
  `stichmonat_abteilung` und `zielprojekt_id`. Statuswerte-Mapping und
  Eskalationsempfänger folgen mit M2/M5.

### Behoben
- Direktaufruf-Schutz der Hauptklasse prüfte die nicht existente Konstante
  `MANTIS_DIR` und verhinderte dadurch das Laden im Plugin-Manager; jetzt
  korrekt `MANTIS_VERSION` (wie im Schwester-Plugin Reveille).

[Unreleased]: https://github.com/marcwoge/QualificationTracker/commits/main
