<!--
  QualificationTracker – Administratorhandbuch (F8.4)
  Installation, Konfiguration, Betrieb, Datensicherung, Upgrade.

  @package   QualificationTracker
  @author    Marc-Philipp Woge <marc.woge@googlemail.com>
  @copyright Copyright (c) 2026 Marc-Philipp Woge
  @license   MIT
-->

# Administratorhandbuch

Dieses Handbuch richtet sich an die Person, die MantisBT und das Plugin
**QualificationTracker** betreibt. Fachliche Anleitungen für Führungskräfte und
Sachbearbeitung stehen im [Anwenderhandbuch](Anwenderhandbuch.md).

Inhalt:

1. [Architektur in einem Absatz](#1-architektur-in-einem-absatz)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Konfiguration](#4-konfiguration)
5. [Erste Schritte](#5-erste-schritte)
6. [Betrieb: die nächtliche Automatik](#6-betrieb-die-nächtliche-automatik)
7. [Import und REST-Schnittstelle](#7-import-und-rest-schnittstelle)
8. [Datenschutz-Funktionen](#8-datenschutz-funktionen)
9. [Datensicherung](#9-datensicherung)
10. [Upgrade](#10-upgrade)
11. [Deinstallation](#11-deinstallation)
12. [Fehlersuche](#12-fehlersuche)

---

## 1. Architektur in einem Absatz

Jeder Nachweis ist ein MantisBT-Ticket (eine Person × eine Maßnahme × ein
Gültigkeitszyklus). Das Plugin ergänzt die fachliche Logik – Maßnahmenkatalog,
Tätigkeitsprofile, Ticketerzeugung, Sammeltermine, Ampel-Matrix, Eskalation –
und hält für schnelle Auswertungen einen denormalisierten Nachweis-Index
(Tabelle `qt_nachweis`). Alle Plugin-Tabellen tragen das Präfix des
MantisBT-`db_table_prefix` plus `_plugin_QualificationTracker_…`. Es gibt **keine
harten Fremd-Plugin-Abhängigkeiten**; ist Reveille installiert, wird es für die
Ablaufreaktivierung automatisch mitgenutzt.

## 2. Voraussetzungen

| Komponente | Version | Anmerkung |
|---|---|---|
| MantisBT | 2.25+ | entwickelt/getestet gegen 2.28.x |
| PHP | 8.1+ | |
| MySQL / MariaDB | 8.0 / 10.6+ | |
| Cron oder systemd-Timer | — | für Ablaufwächter und Eskalation |
| mod_rewrite + REST aktiv | — | **nur** falls die REST-Endpunkte genutzt werden (Abschnitt 7) |

Keine Composer-Pakete zur Laufzeit; ein `git clone` genügt. PHPUnit wird nur für
die Entwicklung benötigt.

## 3. Installation

```bash
cd /var/www/mantisbt/plugins
git clone https://github.com/marcwoge/QualificationTracker.git QualificationTracker
chown -R www-data:www-data QualificationTracker
```

Dann in MantisBT: **Verwaltung → Plugins verwalten → QualificationTracker →
Installieren**. Dabei legt das Plugin an:

- seine Tabellen (Schema, append-only versioniert),
- die benötigten Custom Fields für die Nachweistickets.

Die Custom Fields werden mit einem Zielprojekt verknüpft, sobald dieses in der
Konfiguration gesetzt ist (Abschnitt 4).

## 4. Konfiguration

Aufruf: **Verwaltung → Plugins verwalten → QualificationTracker** (Zahnrad) bzw.
die Konfigurationsseite. Sie ist administratorgesperrt.

### 4.1 Berechtigungsstufen

Vier abgestufte Rollen, jeweils auf ein globales MantisBT-Access-Level
abgebildet:

| Einstellung | Vorgabe | Rolle |
|---|---|---|
| `view_threshold` | VIEWER | Betrachter – nur Lese-Berichte (Matrix, Audit) |
| `edit_threshold` | UPDATER | Sachbearbeiter – Personen, Veranstaltungen, Abschlüsse, Import |
| `manage_threshold` | MANAGER | Fachkraft/SiFa – Katalog, Profile, Generator, Automatik |
| `admin_threshold` | ADMINISTRATOR | Administrator – Konfiguration |

`abteilung_betrachter` bildet einzelne Benutzer-IDs auf eine Abteilung ab;
solche Betrachter sehen die Lese-Berichte **nur für ihre Abteilung**.

### 4.2 Fälligkeitsberechnung

| Einstellung | Vorgabe | Bedeutung |
|---|---|---|
| `faelligkeitsmodus_default` | `kalenderjahr` | `rollierend`, `kalenderjahr`, `stichmonat` oder `extern`; je Maßnahme überschreibbar |
| `stichmonat_default` | 11 | Bezugsmonat für den Modus `stichmonat` (1–12) |
| `stichmonat_abteilung` | – | Bezugsmonat je Abteilung (Lastverteilung) |
| `karenz_tage_default` | 42 | Karenzfenster vor dem Termin, in dem der rollierende Zyklus seinen Anker behält (verhindert Vorwärtsdrift) |
| `ersteinweisung_frist_tage` | 14 | Frist für die Erstunterweisung neuer Beschäftigter (Tage nach Eintritt) |

### 4.3 Eskalation

| Einstellung | Vorgabe | Bedeutung |
|---|---|---|
| `eskalation_stufen_tage` | `[90, 30, 0, -30]` | vier Stufen; positiv = Tage vor Ablauf, negativ = danach |
| `eskalation_empfaenger` | vier leere Listen | zusätzliche Benutzer-IDs, die je Stufe als Ticket-Beobachter aufgenommen werden |

### 4.4 Projekte und Datenschutz

| Einstellung | Vorgabe | Bedeutung |
|---|---|---|
| `zielprojekt_id` | 0 | Projekt, in dem Nachweistickets entstehen (0 = nicht konfiguriert) |
| `vorsorge_projekt_id` | 0 | gesondertes Projekt für Maßnahmen vom Typ `VO` (arbeitsmedizinische Vorsorge); 0 = fällt auf das Zielprojekt zurück |
| `aufbewahrung_monate_default` | 36 | Aufbewahrungsfrist (Monate) nach dem Ankerdatum, dann Löschvorschlag |
| `aufbewahrung_monate_typ` | – | Frist je Maßnahmentyp; 0 = nie löschen |

### 4.5 Automatik und Schnittstellen

| Einstellung | Vorgabe | Bedeutung |
|---|---|---|
| `cron_user` | `administrator` | MantisBT-Benutzer, als den der nächtliche Lauf handelt |
| `reactivation_held_status` | 15 | Status zurückgestellter Erneuerungstickets (native Fallback-Variante ohne Reveille) |
| `ruhens_status` | 20 | Status ruhender abhängiger Beauftragungen |
| `rest_import_enabled` | 0 | schaltet den schreibenden REST-Endpunkt `POST …/import` frei |
| `status_mapping` | siehe Code | Abbildung der fachlichen Nachweiszustände auf MantisBT-Statuswerte |

## 5. Erste Schritte

1. **Zielprojekt anlegen** (z. B. `Qualifikationsmanagement`) und in der
   Konfiguration als `zielprojekt_id` setzen. Für Vorsorge ggf. ein separates
   Projekt als `vorsorge_projekt_id`.
2. **Maßnahmenkatalog** unter *Verwaltung → QualificationTracker → Katalog*
   importieren (mitgelieferter Beispielkatalog `files/beispielkatalog.yaml`) und
   fachlich anpassen. **Dieser Schritt ist der eigentliche Aufwand** und gehört
   in die Hände der Fachkraft für Arbeitssicherheit.
3. **Personen importieren** (CSV) oder anlegen.
4. **Tätigkeitsprofile** definieren und Personen zuordnen.
5. **Trockenlauf** prüfen (*Trockenlauf*), dann **Generieren**.
6. **Bestandsnachweise importieren**, damit nicht alles am Tag eins fällig wird.

Für eine schnelle Demo in der Docker-Umgebung siehe `scripts/qt_seed.php`
(`docker/README.md`).

**Bestehende Bordmittel-Installation?** Wer den Kern bisher rein mit MantisBT
betrieben hat (Nachweise als Tickets mit Custom Fields, Maßnahmentyp als
Kategorie), übernimmt den Bestand über *Verwaltung → QualificationTracker →
Bordmittel-Migration*: Quellprojekt wählen, **Trockenlauf** prüfen, dann
ausführen. Dabei entstehen Personenregister, ein Maßnahmen-Grundgerüst und der
Nachweis-Index; die Tickets selbst bleiben unverändert (jeder Nachweis verweist
auf sein Ursprungsticket). Der Lauf ist wiederholbar. Anschließend das
Maßnahmen-Grundgerüst fachlich vervollständigen (Intervalle, Modus,
Rechtsgrundlagen) und Tätigkeitsprofile anlegen.

## 6. Betrieb: die nächtliche Automatik

Der CLI-Runner bündelt vier Durchläufe:

```bash
php /var/www/mantisbt/plugins/QualificationTracker/scripts/qt_cron.php
```

| Option | Wirkung |
|---|---|
| `--dry-run` | zeigt nur an, was jeder Durchlauf täte; ändert nichts |
| `--only=<pass>` | nur ein Durchlauf: `expiry`, `ruhen`, `reactivation` oder `escalation` |
| `--user=<name>` | handelnder MantisBT-Benutzer (überschreibt `cron_user`) |

Durchläufe in Reihenfolge: **expiry** (abgelaufene Nachweise → Status
„abgelaufen"), **ruhen** (abhängige Beauftragungen ruhen/reaktivieren),
**reactivation** (befristete Erneuerungstickets zurückstellen/wecken),
**escalation** (gestufte Benachrichtigungen um den Termin).

Exit-Codes: `0` Erfolg · `1` mindestens ein Durchlauf mit Fehler · `2` kein
Benutzerkontext herstellbar · `3` unbekannter `--only`-Wert.

Beispiel-Crontab (täglich 05:30):

```bash
# /etc/cron.d/qualificationtracker
30 5 * * *  www-data  php /var/www/mantisbt/plugins/QualificationTracker/scripts/qt_cron.php >> /var/log/mantis/qt_cron.log 2>&1
```

Jeder Lauf wird protokolliert: *Verwaltung → QualificationTracker → Automatik*
(bzw. Laufprotokoll). **Bleibt das Protokoll mehrere Tage leer, läuft der Cron
nicht** – der wichtigste einzelne Überwachungspunkt.

Der Fälligkeitsmodus einer Maßnahme lässt sich nachträglich ändern; die Seite
*Moduswechsel* simuliert die Wirkung auf die offenen Nachweise, bevor etwas
angewandt wird (abgeschlossene Zyklen bleiben unangetastet).

## 7. Import und REST-Schnittstelle

**CSV-Import** über *Import Personen* und *Import Nachweise* (semikolongetrennt,
Kopfzeile, mit Trockenlauf).

**REST-Endpunkte** unter `/api/rest/plugins/QualificationTracker/`:

| Methode | Pfad | Parameter |
|---|---|---|
| GET | `personen` | `abteilung`, `limit` (1000), `offset` (0) |
| GET | `nachweise` | `person_id`, `massnahme_id`, `status`, `limit`, `offset` |
| POST | `import` | Bulk-Import (Personen bzw. Nachweise) |

Voraussetzungen: In MantisBT muss die REST-API aktiv sein
(`webservice_rest_enabled = ON`) **und** `mod_rewrite` greifen, damit
`plugins/…/api/rest/.htaccess` wirkt. Authentifizierung über einen
API-Token im Header `Authorization`. Alle Endpunkte verlangen zusätzlich die
Manage-Stufe; `POST import` ist per `rest_import_enabled` gesperrt (Vorgabe aus)
und liefert sonst `403`.

## 8. Datenschutz-Funktionen

| Funktion | Seite | Zweck |
|---|---|---|
| Berechtigungsstufen | Konfiguration | rollenbasierter Zugriff, Betrachter je Abteilung |
| Vorsorge-Trennung | Konfiguration | VO-Nachweise in gesondertem Projekt, Feldminimierung |
| Löschkonzept | Löschkonzept | typbezogene Fristen, Löschvorschlag, protokollierte Löschung |
| Auskunft (Art. 15) | Auskunft | alle Daten einer Person als Druck-/PDF-Dokument |
| Änderungsprotokoll | Änderungsprotokoll | Historie für Katalog, Profile, Zuordnungen |
| Verarbeitungsverzeichnis | Verarbeitungsverzeichnis | Art.-30-Fassung; Vorlage in `docs/Verarbeitungsverzeichnis.md` |

Das Datenmodell hat **kein** Freitext-Befundfeld; VO-Nachweise führen nur Art,
Termine und Status.

## 9. Datensicherung

Es genügt die reguläre MantisBT-Sicherung – **Datenbank plus Upload-Verzeichnis**
(`uploads`). Das Plugin speichert nichts außerhalb dieser beiden Orte:

- Stammdaten und der Nachweis-Index liegen in den Plugin-Tabellen (Präfix wie
  in Abschnitt 1).
- Die Nachweistickets, ihre Custom-Field-Werte und **Anhänge** liegen in den
  MantisBT-Kern-Tabellen bzw. im Upload-Verzeichnis.

Prüfen Sie, dass die Anhänge wirklich mitgesichert werden – bei
Nachweisdokumenten sind sie der eigentliche Wert.

## 10. Upgrade

```bash
cd /var/www/mantisbt/plugins/QualificationTracker
git pull
```

Danach die Plugin-Verwaltung öffnen; nötige Schema-Migrationen werden angeboten
und angewandt. Das Schema ist **append-only** – bestehende Daten bleiben
erhalten, die Konfiguration ebenfalls. **Vor jedem Upgrade eine
Datenbanksicherung anlegen.**

## 11. Deinstallation

Deinstallieren über die Plugin-Verwaltung. Dabei werden die **Plugin-Tabellen
gelöscht** – das entfernt die vom Plugin gespeicherten personenbezogenen
Stammdaten. Nicht angetastet werden die MantisBT-Ticketdaten (die eigentlichen
Nachweise in den Kern-Tabellen) sowie die gemeinsam genutzten Custom Fields
(sie können anderswo verwendet werden und Daten enthalten).

## 12. Fehlersuche

| Symptom | Ursache / Abhilfe |
|---|---|
| Laufprotokoll bleibt leer | Cron läuft nicht – Crontab, Pfad (`scripts/qt_cron.php`) und Rechte prüfen |
| REST liefert 404/500 | `mod_rewrite` nicht aktiv oder REST-API aus (`webservice_rest_enabled`) |
| REST `POST import` liefert 403 | `rest_import_enabled` ist aus (Vorgabe) – bewusst erst nach Prüfung einschalten |
| Nachweistickets entstehen nicht | `zielprojekt_id` nicht gesetzt oder Custom Fields nicht verknüpft (Konfiguration erneut speichern) |
| Falsche Uhrzeiten/Termine | PHP-`date.timezone` prüfen |
| VO-Nachweise im falschen Projekt | `vorsorge_projekt_id` setzen und Custom Fields dort verknüpfen (Konfiguration speichern) |

---

*Stand: Release 1.0. Änderungen siehe [CHANGELOG.md](../CHANGELOG.md).*
