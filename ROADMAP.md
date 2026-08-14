# Roadmap — MantisBT QualificationTracker

**Version dieses Dokuments:** 0.4 (2026-08-12)
**Status Gesamtprojekt:** 🟡 M1–M5 abgeschlossen (M1 Fundament, M2 Generator, M3 Veranstaltungen, M4 Matrix & Auswertung, M5 Automatisierung; F3.7 zurückgestellt auf 1.1), M6 (Import & Migration) in Arbeit (F6.1 fertig)

---

## 1. Beschreibung des fertigen Projekts

**QualificationTracker** ist ein MantisBT-2.x-Plugin, das eine Bug-Tracking-Installation um ein vollwertiges Qualifikations- und Unterweisungsmanagement erweitert. Es richtet sich an Unternehmen mit gesetzlichen Unterweisungspflichten nach ArbSchG § 12 und DGUV Vorschrift 1 § 4, die bereits MantisBT im Einsatz haben und kein separates Fremdsystem für Arbeitssicherheitsnachweise einführen wollen.

### Was das fertige Plugin leistet

Ein Administrator pflegt einen **Maßnahmenkatalog** (welche Unterweisungen, Qualifikationen, Beauftragungen und Vorsorgen es gibt, mit Intervall, Rechtsgrundlage und Vorbedingungen) sowie **Tätigkeitsprofile** (welche Maßnahmen eine Rolle wie „Hubarbeitsbühnenführer" nach sich zieht).

Wird ein Mitarbeiter einem Tätigkeitsprofil zugeordnet, erzeugt das Plugin **automatisch die vollständige Ticketkette** — Qualifikation, Beauftragung, wiederkehrende Unterweisung — inklusive der Abhängigkeitsbeziehungen zwischen den Tickets und der Terminplanung für die Folgezyklen.

Für **Sammeltermine** legt ein Sachbearbeiter eine Veranstaltung mit Teilnehmerliste an; das Plugin erzeugt die individuellen Nachweistickets, und nach dem Termin werden alle Teilnehmer mit einem Klick und einem einzigen Anhang (der unterschriebenen Teilnehmerliste) abgeschlossen.

Eine **Matrix-Ansicht** direkt in MantisBT zeigt Person × Maßnahme mit Ampelfarben nach Restgültigkeit, filterbar nach Abteilung und Maßnahmentyp, exportierbar als CSV und PDF für Audits.

Eine **Soll-Ist-Prüfung** meldet, welche Personen laut Tätigkeitsprofil eine Maßnahme benötigen, für die kein gültiger Nachweis existiert — die Lücke, die reine Ablauflisten systematisch übersehen.

Ein **Eskalationsdienst** benachrichtigt gestuft (90 / 30 / 0 / −30 Tage) Vorgesetzte, SiFa und Geschäftsführung und kann bei sicherheitsrelevanten Beauftragungen automatisch einen Ruhensvermerk setzen.

Ablaufende befristete Qualifikationen werden über das vorhandene Plugin *Reveille* zurückgestellt und rechtzeitig reaktiviert.

Für die Anbindung an Auswertungssysteme stellt das Plugin **REST-Endpunkte** bereit, über die der Nachweisbestand denormalisiert abgerufen werden kann (Zielsystem im Referenz-Setup: Elasticsearch/Kibana über Apache NiFi).

### Abgrenzung — was das Plugin ausdrücklich nicht leistet

- Keine qualifizierte elektronische Signatur. Der Rechtsnachweis bleibt die unterschriebene Teilnehmerliste als Anhang.
- Keine Pflege des Rechtskatasters. Der Maßnahmenkatalog ist Kundenverantwortung; mitgeliefert wird lediglich ein Beispielkatalog.
- Keine Speicherung medizinischer Befunde. Für arbeitsmedizinische Vorsorge werden ausschließlich Art, Datum und Nachuntersuchungsfrist geführt.
- Keine Schulungsdurchführung, kein LMS, keine E-Learning-Inhalte.
- Kein Ersatz für ein HR-System. Der Personenstamm wird importiert, nicht gepflegt.

### Technische Eckdaten des Zielzustands

| Aspekt | Festlegung |
|---|---|
| Plattform | MantisBT 2.25+, PHP 8.1+, MySQL 8 / MariaDB 10.6+ |
| Abhängigkeiten | **keine harten.** Terminplanung nativ implementiert. Reveille wird optional erkannt und für die Ablaufreaktivierung genutzt |
| UI | Bootstrap 3 (Mantis-Standard) |
| Sprachen | Deutsch, Englisch |
| Lizenz | MIT, konsistent mit den übrigen Plugins des Autors |
| Skalierung | getestet bis 500 Personen / 25.000 Tickets |
| Testumgebung | Docker Compose (Mantis + MariaDB + Seed-Daten) |

---

## 2. Meilensteine

| # | Meilenstein | Ziel | Status |
|---|---|---|---|
| M1 | Fundament | Datenmodell, Schema-Migration, Katalogverwaltung | 🟢 fertig |
| M2 | Generator | Tätigkeitsprofile, automatische Ticketketten, Soll-Ist-Prüfung | 🟢 fertig |
| M3 | Veranstaltungen | Sammeltermine, Massenabschluss, Teilnehmerlisten | 🟢 abgeschlossen |
| M4 | Matrix & Auswertung | Matrix-Ansicht, Ampel, Export | 🟢 abgeschlossen |
| M5 | Automatisierung | Recurrence-/Reveille-Kopplung, Eskalationsstufen | 🟢 abgeschlossen |
| M6 | Import & Migration | CSV-/REST-Import, HR-Sync, Ersterfassung | 🟡 in Arbeit |
| M7 | Audit & Datenschutz | Berechtigungen, Löschkonzept, Auditbericht | ⬜ offen |
| M8 | Release 1.0 | Doku, Docker-Testumgebung, Lasttest, Paketierung | ⬜ offen |

Legende: ⬜ offen · 🟡 in Arbeit · 🟢 fertig · ⚪ zurückgestellt

---

## 3. Funktionsumfang im Detail

### M1 — Fundament

| ID | Funktion | Beschreibung | Status |
|---|---|---|---|
| F1.1 | Schema-Migration | Tabellen `qt_massnahme`, `qt_person`, `qt_profil`, `qt_profil_massnahme`, `qt_zuordnung`, `qt_veranstaltung`; Installations- und Upgrade-Routine über die Mantis-Plugin-API | 🟢 |
| F1.2 | Maßnahmenkatalog CRUD | Anlegen/Bearbeiten von Maßnahmen: Schlüssel, Bezeichnung, Typ (UW/QU/QB/BE/VO), Intervall in Monaten, Rechtsgrundlage, Nachweisart, Vorlaufzeit | 🟢 |
| F1.3 | Vorbedingungen | Maßnahme kann andere Maßnahmen als Voraussetzung referenzieren (Qualifikation → Beauftragung → Unterweisung) | 🟢 |
| F1.4 | Personenregister | Personen unabhängig von Mantis-Benutzerkonten: Personalnummer, Name, Abteilung, Eintritt, Austritt, Vorgesetzter (Mantis-User-ID) | 🟢 |
| F1.5 | Custom-Field-Bootstrap | Die in Teil 1 beschriebenen Custom Fields werden bei Installation automatisch angelegt und den Projekten zugeordnet | 🟢 |
| F1.6 | Konfigurationsseite | Plugin-Konfiguration: Zielprojekt, Statuswerte-Mapping, Vorlaufzeiten, Eskalationsempfänger | 🟢 |
| F1.7 | Beispielkatalog | Mitgelieferter Startkatalog (Jahresunterweisung, Brandschutz, Erste Hilfe, Hubarbeitsbühne, Flurförderzeuge, Leitern & Tritte, Gefahrstoffe) als importierbare YAML | 🟢 |
| F1.8 | Fälligkeitsmodus je Maßnahme | Vier Modi: `rollierend`, `kalenderjahr`, `stichmonat` (mit Monatsangabe), `extern` (kein Rechnen, Datum aus Nachweis). Global konfigurierbarer Vorgabewert, je Maßnahme überschreibbar, je Abteilung für `stichmonat` staffelbar | 🟢 |
| F1.9 | Karenzzeit und Ankererhalt | Feld `soll_termin` je Nachweis. Bei Durchführung innerhalb der Karenzzeit vor dem Soll-Termin wird der Folgezyklus vom Soll- statt vom Ist-Datum berechnet — verhindert Vorwärtsdrift des Intervalls über die Jahre | 🟢 |

### M2 — Generator

| ID | Funktion | Beschreibung | Status |
|---|---|---|---|
| F2.1 | Tätigkeitsprofile | Profil = benannte Menge von Maßnahmen („Hubarbeitsbühnenführer", „Servicetechniker Außendienst", „Bürotätigkeit") | 🟢 |
| F2.2 | Profilzuordnung | Person ↔ Profil (n:m), mit Gültigkeitszeitraum | 🟢 |
| F2.3 | Ketten-Generator | Erzeugt aus Profilzuordnung die Tickets inkl. `depends on`-Beziehungen in korrekter Reihenfolge | 🟢 |
| F2.4 | Native Terminplanung | Erzeugt Folgezyklen selbst — vorausschauend oder ereignisgetrieben je nach Fälligkeitsmodus. Ist IssueRecurrence installiert, wird es optional erkannt und kann alternativ genutzt werden; eine Abhängigkeit besteht nicht | 🟢 |
| F2.5 | Soll-Ist-Prüfung | Report: Personen mit Profil, aber ohne gültigen Nachweis; inkl. „Beauftragung ohne Qualifikation" | 🟢 |
| F2.6 | Vorschau vor Anlage | Dry-Run-Ansicht: welche Tickets würden entstehen, bevor sie erzeugt werden | 🟢 |
| F2.7 | Profiländerung | Wechselt eine Person das Profil, werden entfallende Maßnahmen auf `entfallen` gesetzt und neue erzeugt | 🟢 |
| F2.8 | Zwei Erzeugungsstrategien | **Vorausschauend** bei `kalenderjahr`/`stichmonat`: Jahrgang wird im Voraus gesammelt erzeugt (Voraussetzung für Terminplanung M3). **Ereignisgetrieben** bei `rollierend`/`extern`: Folgeticket entsteht erst beim Abschluss des Vorgängers, da vorher kein Fälligkeitsdatum existiert | 🟢 |

### M3 — Veranstaltungen

| ID | Funktion | Beschreibung | Status |
|---|---|---|---|
| F3.1 | Veranstaltung anlegen | Maßnahme, Termin, Ort, Unterweisender, Kapazität | 🟢 |
| F3.2 | Teilnehmerauswahl | Auswahl aus fälligen Personen mit Filter nach Abteilung und Fälligkeit; Warnung bei Überbuchung | 🟢 |
| F3.3 | Kind-Tickets | Erzeugt je Teilnehmer ein Nachweisticket als Kind des Veranstaltungstickets | 🟢 |
| F3.4 | Massenabschluss | Ein Klick setzt für alle Teilnehmer Status, `durchgefuehrt_am` und `gueltig_bis`; Abwesende bleiben offen und werden neu terminiert | 🟢 |
| F3.5 | Teilnehmerliste PDF | Druckbare Anwesenheitsliste mit Unterschriftenspalte, Maßnahmeninhalt und Rechtsgrundlage | 🟢 |
| F3.6 | Nachweis-Anhang | Gescannte Liste wird einmal am Elternticket hinterlegt und in allen Kindern referenziert | 🟢 |
| F3.7 | Terminvorschlag | Schlägt auf Basis fälliger Maßnahmen Veranstaltungstermine mit optimaler Teilnehmerzahl vor | ⚪ zurückgestellt auf 1.1 |

### M4 — Matrix & Auswertung

| ID | Funktion | Beschreibung | Status |
|---|---|---|---|
| F4.1 | Matrix-Ansicht | Person × Maßnahme, Zellenfarbe nach Restgültigkeit, Zelle klickbar zum Ticket | 🟢 |
| F4.2 | Filter & Gruppierung | Nach Abteilung, Profil, Maßnahmentyp, Status; Umschalten Zeilen/Spalten | 🟢 |
| F4.3 | Paginierung & Performance | Serverseitige Aggregation, damit die Matrix bei 500 Personen in < 2 s rendert | 🟢 |
| F4.4 | CSV-Export | Matrix und Rohdaten als CSV | 🟢 |
| F4.5 | Audit-PDF | Stichtagsbezogener Nachweisbericht mit Erfüllungsgrad je Abteilung | 🟢 |
| F4.6 | Kennzahlen-Widget | Mantis-Startseiten-Widget: eigener Erfüllungsgrad und überfällige Maßnahmen | 🟢 |

### M5 — Automatisierung

| ID | Funktion | Beschreibung | Status |
|---|---|---|---|
| F5.1 | Ablaufwächter | Nächtlicher Lauf: setzt abgelaufene Nachweise auf `abgelaufen` | 🟢 |
| F5.2 | Ablaufreaktivierung | Befristete Qualifikationen werden zurückgestellt und zum Ablauf minus Vorlauf reaktiviert. Ist Reveille installiert, wird delegiert; sonst greift der eigene Fallback. Das Weckdatum liefert in beiden Fällen der Fälligkeitsrechner | 🟢 |
| F5.3 | Eskalationsstufen | Vier konfigurierbare Stufen (90/30/0/−30 Tage) mit unterschiedlichen Empfängerkreisen | 🟢 |
| F5.4 | Ruhensvermerk | Bei überfälliger sicherheitsrelevanter Beauftragung automatischer Vermerk und Statuswechsel der abhängigen Beauftragung | 🟢 |
| F5.5 | CLI-Runner | `php qt_cron.php` für Cron beziehungsweise systemd-Timer, mit Exit-Codes für Monitoring | 🟢 |
| F5.6 | Laufprotokoll | Jeder Automatiklauf wird protokolliert und ist in der Oberfläche einsehbar | 🟢 |
| F5.7 | Moduswechsel im Bestand | Wird der Fälligkeitsmodus einer Maßnahme im laufenden Betrieb geändert, zeigt eine Simulation die betroffenen Nachweise und die neuen Termine vor der Übernahme. Bereits abgeschlossene Zyklen werden **nicht** rückwirkend neu berechnet, sonst bricht die Auditspur | 🟢 |

### M6 — Import & Migration

| ID | Funktion | Beschreibung | Status |
|---|---|---|---|
| F6.1 | CSV-Import Personen | Personalstamm inkl. Abteilung und Vorgesetztem | 🟢 |
| F6.2 | CSV-Import Bestandsnachweise | Historische Nachweise mit Durchführungs- und Ablaufdatum; erzeugt Tickets im Zielstatus | ⬜ |
| F6.3 | REST-Endpunkte | `GET /qt/nachweise`, `GET /qt/personen`, `POST /qt/import` — für NiFi-Anbindung | ⬜ |
| F6.4 | HR-Sync | Wiederkehrender Abgleich: Neueintritt → Ketten anlegen, Austritt → offene Tickets auf `entfallen` | ⬜ |
| F6.5 | Dublettenprüfung | Import erkennt bestehende Nachweise über (Personalnummer, Maßnahme, Zeitraum) | ⬜ |
| F6.6 | Rollback | Jeder Import erhält eine Batch-ID und ist als Ganzes zurücknehmbar | ⬜ |

### M7 — Audit & Datenschutz

| ID | Funktion | Beschreibung | Status |
|---|---|---|---|
| F7.1 | Berechtigungsstufen | Eigene Access Level: Betrachter (nur eigene Abteilung), Sachbearbeiter, SiFa, Administrator | ⬜ |
| F7.2 | Vorsorge-Trennung | Maßnahmen vom Typ `VO` nur in gesondertem Projekt sichtbar, Feldsatz technisch beschränkt | ⬜ |
| F7.3 | Löschkonzept | Aufbewahrungsfrist je Maßnahmenart; Löschvorschlagsliste; Löschung wird protokolliert | ⬜ |
| F7.4 | Auskunftsexport | Alle zu einer Person gespeicherten Daten als PDF (DSGVO Art. 15) | ⬜ |
| F7.5 | Änderungsprotokoll | Plugin-eigene Änderungen (Katalog, Profile) werden analog zur Mantis-History protokolliert | ⬜ |
| F7.6 | Verarbeitungsverzeichnis | Vorlagentext als Anhang der Dokumentation | ⬜ |

### M8 — Release 1.0

| ID | Funktion | Beschreibung | Status |
|---|---|---|---|
| F8.1 | Docker-Testumgebung | Compose-Setup mit Mantis, MariaDB, Seed-Daten (50 Personen, 8 Maßnahmen) | ⬜ |
| F8.2 | Lasttest | Nachweis der Zielgrößen: 500 Personen, 25.000 Tickets, Matrix < 2 s | ⬜ |
| F8.3 | Übersetzungen | Vollständige Sprachdateien DE/EN | ⬜ |
| F8.4 | Administratorhandbuch | Installation, Konfiguration, Betrieb, Backup, Upgrade | ⬜ |
| F8.5 | Anwenderhandbuch | Kurzanleitungen für Führungskräfte und Sachbearbeitung, je max. 2 Seiten | ⬜ |
| F8.6 | Upgrade-Pfad | Migrationsroutine von der reinen Bordmittel-Konfiguration in die Plugin-Datenstruktur | ⬜ |
| F8.7 | Paketierung | Release-Artefakt, Versionierung, Changelog | ⬜ |

---

## 4. Offene fachliche Entscheidungen

Diese Punkte müssen vor M2 geklärt sein, da sie das Datenmodell beeinflussen:

| # | Frage | Auswirkung |
|---|---|---|
| E1 | Ist die Personalnummer der führende Schlüssel, oder braucht es eine plugin-eigene ID für Personen ohne Personalnummer (Leiharbeiter, Fremdfirmen)? | Datenmodell `qt_person` |
| E2 | Werden Fremdfirmenmitarbeiter mitgeführt? Wenn ja: eigener Personentyp mit reduziertem Feldsatz | M1, M7 |
| E3 | Wie wird mit Unterweisungen umgegangen, die anlassbezogen sind (nach Unfall, bei neuer Anlage) und kein Intervall haben? | Maßnahmentyp `UA` ergänzen? |
| ~~E4~~ | ~~Kalenderjährlich oder rollierend?~~ **Geklärt 2026-08-12:** konfigurierbar je Maßnahme, vier Modi — siehe F1.8, F1.9, F2.8 | erledigt |
| E5 | Sollen Jugendliche automatisch das Halbjahresintervall bekommen (Ableitung aus Geburtsdatum)? | Speicherung Geburtsdatum = zusätzliche personenbezogene Daten |
| E6 | Wird der Maßnahmenkatalog je Standort unterschiedlich sein? | Mandantenfähigkeit des Katalogs |

---

## 5. Risiken

| Risiko | Bewertung | Gegenmaßnahme |
|---|---|---|
| Vorwärtsdrift bei rollierendem Modus bleibt unbemerkt | mittel, wirkt erst nach Jahren | Karenz mit Ankererhalt (F1.9) von Beginn an, nicht nachrüsten |
| Fachlicher Maßnahmenkatalog liegt nicht rechtzeitig vor | hoch, häufigster Projektkiller | Beispielkatalog mitliefern, Pilot mit 3 Maßnahmen starten |
| Akzeptanz der Führungskräfte | hoch | Startseiten-Widget (F4.6), Standardfilter, max. 2 Seiten Anleitung |
| Datenschutzbedenken Betriebsrat | mittel | Vorsorge-Trennung und Löschkonzept vor Rollout vorlegen; Mitbestimmung nach BetrVG § 87 prüfen |
| Performance der Matrix bei 500 Personen | mittel | serverseitige Aggregation von Beginn an, Lasttest in M8 |
| Öffentliche Veröffentlichung mit privater Pflichtabhängigkeit | ausgeschlossen | keine harten Plugin-Abhängigkeiten; Fremdplugins nur optional erkennen |

---

## 6. Changelog dieses Dokuments

| Datum | Version | Änderung |
|---|---|---|
| 2026-08-12 | 0.1 | Ersterstellung: Zielbeschreibung, 8 Meilensteine, 46 Funktionen, offene Entscheidungen |
| 2026-08-12 | 0.6 | **M1 (Fundament) abgeschlossen:** F1.1–F1.9 implementiert und in MantisBT 2.28.3 verifiziert. Fälligkeitsrechner `QT_DueDateCalculator` mit 100 % Zeilenabdeckung; 85 PHPUnit-Tests. Docker-Testumgebung, Katalog-/Personen-/Konfigurationsoberflächen, Custom-Field-Bootstrap, Beispielkatalog-Import |
| 2026-08-12 | 0.5 | M1 begonnen. E1/E2/E3/E5/E6 entschieden (Surrogat-ID führend, Personalnummer nullable-unique; Fremdpersonal per `typ`-Diskriminator; anlassbezogen per `wiederkehrend`; Jugendschutz per Stichdatum statt Geburtsdatum; ein globaler Katalog). F1.1 (Schema-Migration) implementiert und in MantisBT 2.28.3 / MariaDB 10.11 verifiziert |
| 2026-08-12 | 0.4 | Lizenz zurück auf MIT für Konsistenz mit den fünf bereits veröffentlichten Plugins des Autors. Reveille als optionale Integration für F5.2 aufgenommen; die vorhandenen Plugins dienen zusätzlich als API-Referenz für die Implementierung |
| 2026-08-12 | 0.3 | Lizenz auf GPL-2.0-or-later umgestellt (Mantis-Konformität). Harte Abhängigkeit zu IssueRecurrence entfällt: Terminplanung wird nativ implementiert, Fremdplugins nur optional erkannt. Vorbereitung der öffentlichen Veröffentlichung |
| 2026-08-12 | 0.2 | E4 geklärt: Fälligkeitsmodus wird je Maßnahme konfigurierbar. Neu: F1.8 (vier Modi), F1.9 (Karenz und Ankererhalt), F2.8 (zwei Erzeugungsstrategien), F5.7 (Moduswechsel im Bestand). Jetzt 50 Funktionen |
