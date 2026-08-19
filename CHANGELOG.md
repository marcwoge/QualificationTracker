# Changelog

Alle nennenswerten Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
die Versionierung folgt [Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

### Hinzugefügt
- **F7.5 Änderungsprotokoll (M7):** MantisBT protokolliert die Historie jedes
  Tickets, aber die plugin-eigenen Stammdaten liegen in eigenen Tabellen und
  sind dieser Historie nicht zugänglich. Anlage, Änderung und Löschung von
  **Maßnahmen, Tätigkeitsprofilen** (inkl. ihrer Maßnahmen-Zusammensetzung) und
  **Zuordnungen** werden nun in eine append-only Tabelle `qt_historie`
  geschrieben — je geändertem Feld eine Zeile mit Alt-/Neu-Wert, Benutzer und
  Zeitpunkt, analog zur Ticket-Historie. Die Datenschicht ruft die
  Protokoll-Helfer über einen Lazy-Require auf, sodass die reinen Datendateien
  weiterhin ohne dieses Modul ladbar bleiben. Neue Seite **Änderungsprotokoll**
  (`pages/historie.php`, nach Objektart filterbar). Neue reine, unit-getestete
  `qt_historie_diff` in `core/QT_History.php`.
- **F7.4 Auskunftsexport (M7):** Für eine Betroffenenauskunft nach DSGVO
  Art. 15 stellt die Seite **Auskunft** (`pages/auskunft.php`,
  administratorgesperrt) alle zu einer Person im Plugin gespeicherten Daten
  zusammen: Stammdaten, Tätigkeitsprofil-Zuordnungen, sämtliche Nachweise,
  Veranstaltungsteilnahmen und etwaige Einträge im Löschprotokoll. Die
  Standardansicht zeigt eine Vorschau; `format=print` liefert ein
  **eigenständiges, druckoptimiertes HTML-Dokument** (ohne MantisBT-Rahmen),
  das der Browser als PDF speichert — so bleibt das Plugin ohne
  Laufzeitabhängigkeit git-clone-fähig (MantisBT bringt keine PDF-Bibliothek
  mit). Arbeitsmedizinische (VO-)Nachweise erscheinen nur als Metadaten (Art,
  Termine, Status); das Datenmodell hat ohnehin kein Freitext-Befundfeld.
  Neue reine, unit-getestete `qt_auskunft_person_fields` und
  `qt_auskunft_filename` in `core/QT_Disclosure.php`.
- **F7.3 Löschkonzept (M7):** Personenbezogene Nachweisdaten werden nicht mehr
  unbegrenzt aufbewahrt (DSGVO Art. 17). Je Maßnahmentyp gilt eine
  **Aufbewahrungsfrist** (`aufbewahrung_monate_typ` mit globalem Default
  `aufbewahrung_monate_default`, Standard 36 Monate); nach Ablauf der Frist ab
  dem Ankerdatum eines **abgeschlossenen** Nachweises (Gültigkeitsende bei
  *abgelaufen*, Änderungsdatum bei *entfallen*) erscheint er auf der
  **Löschvorschlagsliste** (`pages/loeschung.php`, administratorgesperrt). Eine
  bestätigte Löschung entfernt das MantisBT-Ticket samt Notizen, Feldern,
  Anhängen und Beziehungen, den Index-Datensatz sowie etwaige Teilnehmer-Verweise
  und schreibt je Nachweis eine Zeile in ein **append-only Löschprotokoll**
  (Tabelle `qt_loeschung`: Personalnummer, Maßnahmenschlüssel, Ticket-Nr.,
  Gültigkeitsende, Grund, Benutzer, Zeitpunkt), damit die Löschung nach dem
  Wegfall von Ticket und Stammdaten nachweisbar bleibt. Die Fälligkeit wird bei
  der Ausführung serverseitig erneut geprüft, sodass ein veralteter oder
  gefälschter Datensatz-Verweis nie einen noch fristgebundenen Nachweis löschen
  kann. Neue reine, unit-getestete Helfer in `core/QT_Deletion.php`
  (`qt_loesch_retention_months`, `qt_loesch_anchor`, `qt_loesch_delete_on`,
  `qt_loesch_is_due`, `qt_loesch_state_final`), Konfiguration der Fristen auf der
  Konfigurationsseite.
- **F7.2 Vorsorge-Trennung (M7):** Nachweistickets für Maßnahmen vom Typ
  **VO** (arbeitsmedizinische Vorsorge) werden in ein gesondertes, über die
  Konfiguration `vorsorge_projekt_id` wählbares MantisBT-Projekt angelegt statt
  ins allgemeine Zielprojekt — so sehen sie nur arbeitsmedizinisch Berechtigte
  (Projekt-Zugriffssteuerung). Zusätzlich Datenminimierung: ein VO-Ticket trägt
  nur Identifikations- und Terminfelder; Programm-Metadaten (Rechtsgrundlage,
  Intervall, Fälligkeitsmodus) werden weggelassen, und das Datenmodell hat
  ohnehin nirgends ein Freitext-Befundfeld. Neue reine, unit-getestete
  `qt_vorsorge_project` und `qt_vorsorge_field_allowed` in `core/QT_Vorsorge.php`;
  der Ketten-Generator (und darüber Completion und Import) leitet VO-Tickets
  entsprechend um. Auswahl des Vorsorge-Projekts auf der Konfigurationsseite.
- **F7.1 Berechtigungsstufen (M7):** Vier abgestufte Rollen, jeweils auf ein
  globales MantisBT-Access-Level abgebildet und konfigurierbar: *Betrachter*
  (`view_threshold`, Default VIEWER – nur Lesen), *Sachbearbeiter*
  (`edit_threshold`, Default UPDATER – Personen, Veranstaltungen, Abschlüsse,
  Import), *SiFa* (`manage_threshold`, Default MANAGER – Katalog, Profile,
  Generator, Automatik) und *Administrator* (`admin_threshold`, Default
  ADMINISTRATOR – Konfiguration). Jede Seite prüft nun ihre Rolle über
  `core/QT_Access.php` (`qt_access_ensure`); die Lese-Berichte (Matrix,
  Soll-Ist) lassen sich für Betrachter über die Zuordnung
  `abteilung_betrachter` (Benutzer → Abteilung) auf die **eigene Abteilung**
  einschränken. Da das Verwaltungsmenü managergesperrt ist, gibt es zusätzlich
  einen Seitenleisten-Eintrag (`EVENT_MENU_MAIN`) für Betrachter. Neue reine,
  unit-getestete `qt_access_effective_abteilung`.
- **F6.3 REST-Endpunkte (M6):** Für die NiFi-Anbindung stellt das Plugin über
  den MantisBT-REST-Hook (`EVENT_REST_API_ROUTES`, Slim) drei Endpunkte unter
  `/api/rest/plugins/QualificationTracker/` bereit: `GET personen` (Personenregister
  als JSON, Filter `abteilung`, `limit`, `offset`), `GET nachweise`
  (`qt_nachweis`-Index, Filter `person_id`, `massnahme_id`, `status`) und
  `POST import` (Bulk-Import von Personen bzw. Nachweisen, Wiederverwendung von
  F6.1/F6.2, mit `dry_run`). Die MantisBT-Auth-Middleware authentifiziert
  (API-Token/Session); jeder Handler verlangt zusätzlich das `manage_threshold`.
  Der schreibende Import ist per Konfiguration `rest_import_enabled` (Default
  **aus**) freizuschalten — sonst `403`. Neue reine, unit-getestete Serialisierer
  (`qt_rest_person_json`, `qt_rest_nachweis_json`) in `core/QT_Rest.php`.
  **Schließt M6 ab.**
- **F6.2 CSV-Import Bestandsnachweise (M6):** Historische Nachweise werden über
  *Verwaltung → QualificationTracker → Import Nachweise* aus einer CSV
  (Personalnummer, Maßnahmen-Schlüssel, Durchführungs- und Ablaufdatum,
  Durchführende/r, Zyklus) importiert. Je Zeile entsteht ein Nachweisticket im
  **Zielstatus** — `gültig`, solange „gültig bis" leer oder nicht in der
  Vergangenheit ist, sonst `abgelaufen` — mit gesetzten Audit-Feldern
  (durchgeführt am, gültig bis, Durchführende/r) und einem `qt_nachweis`-Eintrag.
  Der Import ist je Zyklus idempotent (bereits vorhandene werden übersprungen)
  und bietet einen Probelauf. Neue reine, unit-getestete Helfer
  (`qt_import_nachweise_map_row`, `qt_import_nachweise_target_status`,
  `qt_import_nachweise_zyklus`) und `qt_import_nachweise_run` in
  `core/QT_ImportNachweise.php`; nutzt Generator + Nachweis-Index wieder.
- **F6.1 CSV-Import Personen (M6):** Personalstamm-Import über *Verwaltung →
  QualificationTracker → Import Personen*: semikolongetrennte CSV mit Kopfzeile
  (UTF-8), inkl. Abteilung und Vorgesetztem (als MantisBT-Benutzername, der zur
  User-ID aufgelöst wird). Bestehende Personen werden anhand der Personalnummer
  aktualisiert (Upsert), neue angelegt; jede Zeile durchläuft dieselbe
  Validierung wie das Personenregister (F1.4). Ein **Probelauf** prüft und meldet
  Anlegen/Aktualisieren/Fehler zeilengenau, ohne zu schreiben. Neue reine,
  unit-getestete Parser/Mapper (`qt_import_personen_parse`,
  `qt_import_personen_map_row`, `qt_import_personen_bool`) und
  `qt_import_personen_run` in `core/QT_ImportPersonen.php`; abhängigkeitsfrei
  (kein CSV-Paket).
- **F5.7 Moduswechsel im Bestand (M5):** Wird der Fälligkeitsmodus einer
  Maßnahme im laufenden Betrieb geändert, zeigt eine **Simulation** unter
  *Verwaltung → QualificationTracker → Moduswechsel* zuerst, welche **offenen**
  Nachweise betroffen sind und welchen neuen Soll-Termin sie erhielten (alt/neu),
  bevor etwas übernommen wird. Erst auf Bestätigung werden der Maßnahmenmodus und
  die offenen Nachweise (Soll-Termin, Ticket-Fälligkeit, Custom-Field)
  aktualisiert. **Bereits abgeschlossene Zyklen** (gültig/abgelaufen/entfallen)
  werden nie rückwirkend neu berechnet — die Auditspur bleibt intakt. Der neue
  Termin behält das Zyklusjahr und platziert nur innerhalb dessen neu
  (Kalenderjahr → 31.12., Stichmonat → Monatsende, extern → kein Termin,
  rollierend → unverändert bis zum nächsten Abschluss). Neue reine,
  unit-getestete `qt_moduswechsel_new_soll` sowie `qt_moduswechsel_simulate`/
  `qt_moduswechsel_apply` in `core/QT_ModeChange.php`. **Schließt M5 ab.**
- **F5.6 Laufprotokoll (M5):** Jeder ausgeführte Automatiklauf – aus dem
  CLI-Runner (F5.5) wie aus den manuellen Auslösern der Automatik-Seite – wird in
  der neuen Tabelle `qt_lauf` mit Zeitpunkt, Lauf, Quelle (cli/ui),
  Ergebnis-Zusammenfassung (JSON) und auslösendem Nutzer protokolliert und ist
  unten auf der Automatik-Seite einsehbar (letzte 25 Läufe). Neue reine,
  unit-getestete `qt_lauf_format_result` sowie `qt_lauf_record`/`qt_lauf_load`
  in `core/QT_RunLog.php`.
- **F5.5 CLI-Runner (M5):** `scripts/qt_cron.php` bündelt die vier
  Automatikläufe (Ablaufwächter, Ruhensvermerk, Ablaufreaktivierung,
  Eskalation) für Cron bzw. systemd-Timer. CLI-only (Web-Aufruf wird
  abgewiesen), bootstrappt MantisBT selbst und agiert als konfigurierbarer
  Dienstnutzer (`cron_user`, Default `administrator`; `--user` überschreibt).
  Optionen `--dry-run` (meldet je Lauf, was passieren würde, ohne Änderung),
  `--only=<lauf>`, `--help`. Monitoring-taugliche Exit-Codes: 0 ok, 1 Fehler in
  einem Lauf, 2 kein Nutzerkontext, 3 unbekannter Lauf. Zeitgestempeltes
  Protokoll je Lauf auf stdout.
- **F5.4 Ruhensvermerk (M5):** Eine Beauftragung (Typ BE) ruht automatisch,
  sobald eine ihrer **sicherheitsrelevanten** Voraussetzungen für die Person
  keinen gültigen Nachweis mehr hat: das Beauftragungsticket erhält einen
  Ruhensvermerk (Notiz) und wechselt auf den konfigurierten Ruhens-Status
  (`ruhens_status`, Default 20). Sobald die Voraussetzung wieder gültig ist, wird
  der Vermerk aufgehoben und der vorherige Status wiederhergestellt. Nutzt den
  Vorbedingungsgraphen (F1.3) und die Gültigkeitsbewertung der Soll-Ist-Prüfung
  (F2.5). Neue Spalte `qt_nachweis.ruht` (Schema 24) macht den Vorgang idempotent
  und umkehrbar. Neue reine, unit-getestete `qt_ruhen_should_rest` und
  `qt_ruhen_run` in `core/QT_Ruhen.php`; vierter Abschnitt auf der
  Automatik-Seite.
- **F5.3 Eskalationsstufen (M5):** Offene Nachweise mit Fälligkeit werden
  gestaffelt an den konfigurierten Tages-Stufen (Default 90/30/0/−30, positiv =
  vor, negativ = nach Fälligkeit) eskaliert. Je erreichte Stufe wird eine Notiz
  am Nachweisticket ergänzt (MantisBT benachrichtigt Bearbeiter/Beobachter);
  über `eskalation_empfaenger` lassen sich je Stufe zusätzliche Empfänger
  festlegen, die dann als Beobachter aufgenommen werden – so weitet sich der
  Empfängerkreis mit steigender Stufe. Eine neue Spalte
  `qt_nachweis.eskalation_stufe` merkt die höchste bereits ausgelöste Stufe, so
  dass jede Stufe genau einmal feuert (idempotent). Neue reine, unit-getestete
  `qt_eskalation_reached_count` und `qt_eskalation_run` in
  `core/QT_Escalation.php`; dritter Abschnitt auf der Automatik-Seite.
- **F5.2 Ablaufreaktivierung (M5):** Renewal-Tickets befristeter Qualifikationen
  (Typ QB) werden bis „Ablauf minus Vorlauf" zurückgestellt und dann reaktiviert,
  statt über die gesamte – oft mehrjährige – Gültigkeit offen zu bleiben. Das
  Weckdatum kommt in jedem Fall aus dem Fälligkeitsrechner (`soll_termin` minus
  Vorlaufzeit der Maßnahme bzw. größte Eskalationsstufe). **Reveille-Kopplung als
  Hand-off:** ist das Reveille-Plugin installiert, wird das Ticket auf Reveilles
  Schlaf-Status gelegt (mit gesetztem `due_date`) und Reveille übernimmt das
  Wecken; ohne Reveille stellt der eigene Fallback zurück und reaktiviert selbst.
  Neue reine, unit-getestete Helfer (`qt_reactivation_wake_date`,
  `qt_reactivation_vorlauf`, `qt_reactivation_is_dormant`) und
  `qt_reactivation_run` in `core/QT_Reactivation.php`; zweiter Abschnitt auf der
  Automatik-Seite. Neue Konfiguration `reactivation_held_status` (Default 15,
  wie Reveille) für den Fallback.
- **F5.1 Ablaufwächter (M5):** Setzt gültige Nachweise, deren Gültigkeit
  abgelaufen ist (`gueltig_bis` in der Vergangenheit), auf den Status
  `abgelaufen` — im abgeleiteten Index und auf dem MantisBT-Ticket
  (Status-Mapping). Der Lauf ist idempotent und für den nächtlichen Betrieb
  gedacht (CLI-Runner in F5.5); unter *Verwaltung → QualificationTracker →
  Automatik* mit Vorschau der betroffenen Nachweise auch manuell auslösbar.
  Neue reine, unit-getestete Funktion `qt_expiry_is_expired` sowie
  `qt_expiry_find`/`qt_expiry_run` in `core/QT_Expiry.php`.
- **F4.6 Kennzahlen-Widget (M4):** Auf der Mantis-Startseite (My-View bzw.
  Hauptseite) erscheint für berechtigte Nutzer ein Überblicks-Widget mit dem
  aktuellen Gesamt-Erfüllungsgrad und den Zahlen der offenen Nachweise
  (abgelaufen / kein Nachweis / in Bearbeitung), samt Verknüpfung zu Matrix und
  Auditbericht. Umgesetzt über den `EVENT_LAYOUT_CONTENT_BEGIN`-Hook, streng auf
  die Startseiten begrenzt und ohne Datenbankzugriff, solange diese Prüfung
  nicht greift. **Schließt M4 ab.**
- **F4.5 Audit-Bericht (M4):** Stichtagsbezogener Nachweisbericht unter
  *Verwaltung → QualificationTracker → Auditbericht*: Erfüllungsgrad je Abteilung
  (Soll-Pflichten, erfüllte/gültige Nachweise, Aufschlüsselung nach
  offen/abgelaufen/fehlt, Prozentsatz) zzgl. Gesamtzeile, dazu eine Liste der
  offenen Nachweise zum Stichtag. Die Gültigkeit wird zum wählbaren Stichtag
  bewertet (Auswertung teilt sich die Zellenlogik mit der Matrix). Wie die
  Teilnehmerliste abhängigkeitsfrei als druckoptimierte HTML-Seite
  (*„Drucken → als PDF speichern"*). Neue reine, unit-getestete Funktion
  `qt_audit_rate` und `qt_matrix_compliance` in `core/QT_Matrix.php`,
  `pages/audit.php`.
- **F4.4 CSV-Export (M4):** Zwei CSV-Downloads aus der Matrix, jeweils unter
  Beibehaltung der aktiven Filter: die **Matrix** (eine Zeile je Person, eine
  Spalte je Maßnahme, Zelle = Status inkl. Restlaufzeit) und die **Rohdaten**
  (eine Zeile je Nachweis mit Person, Maßnahme, Status, Soll-Termin, Gültig-bis,
  Zyklus und Ticket). Abhängigkeitsfrei über `fputcsv`; Semikolon-Trennung und
  UTF-8-BOM, damit die Datei in deutschem Excel direkt korrekt öffnet.
  `qt_matrix_raw_rows` in `core/QT_Matrix.php`, `pages/matrix_export.php`.
- **F4.3 Paginierung & Performance (M4):** Der Matrixaufbau nutzt jetzt drei
  Aggregat-Abfragen (Personen, Soll-Maßnahmen, Nachweise) statt zwei Abfragen je
  Person — die Abfrageanzahl bleibt konstant, unabhängig von der Belegschaft
  (kein N+1 mehr). Die Zeilen werden serverseitig paginiert (50 Personen je
  Seite) mit Blättern unter Beibehaltung aller Filter. Messung im Testcontainer:
  500 Personen in ~0,015 s mit 3 Abfragen (zuvor ~1000 Abfragen). Die
  Zellergebnisse sind identisch zur vorherigen Implementierung (verifiziert).
  Neue Helfer `qt_matrix_required_pairs`, `qt_matrix_nachweise`,
  `qt_matrix_person_filter_sql`.
- **F4.2 Filter & Gruppierung (M4):** Die Qualifikationsmatrix ist zusätzlich
  nach Profil, Maßnahmentyp und Zellstatus filterbar (kombinierbar mit dem
  Abteilungsfilter aus F4.1); der Statusfilter zeigt nur Personen mit
  mindestens einer Zelle im gewählten Zustand (z. B. „abgelaufen", um Rückstände
  zu finden). Der Maßnahmentyp-Filter beschränkt die Spalten, die verbleibenden
  Spalten sind stets die tatsächlich vorkommenden Maßnahmen. Zeilen und Spalten
  lassen sich vertauschen (Person × Maßnahme ↔ Maßnahme × Person), die Filter
  bleiben dabei erhalten. Neue reine, unit-getestete Helfer
  (`qt_matrix_row_has_state`) und `qt_matrix_profil_person_ids`.
- **F4.1 Matrix-Ansicht (M4):** Qualifikationsmatrix Person × Maßnahme unter
  *Verwaltung → QualificationTracker → Matrix*. Jede Zelle ist nach dem
  Nachweisstatus eingefärbt — gültig (grün), läuft bald ab (gelb, innerhalb des
  Warnfensters), in Bearbeitung (blau), abgelaufen (rot), kein Nachweis (grau)
  bzw. nicht zugeordnet — und verlinkt auf das zugehörige Ticket; gültige Zellen
  zeigen die Restlaufzeit in Tagen. Das Warnfenster ergibt sich aus der größten
  positiven Eskalationsstufe (Standard 90 Tage). Spalten sind die tatsächlich
  zugeordneten Maßnahmen, filterbar nach Abteilung. Aufbau auf dem
  `qt_nachweis`-Index, Lückenbewertung geteilt mit der Soll-Ist-Prüfung (F2.5).
  Neue `core/QT_Matrix.php` mit reinen, unit-getesteten Helfern
  (`qt_matrix_cell`, `qt_matrix_warn_days`). Filter/Gruppierung (F4.2) und
  serverseitige Aggregation für große Bestände (F4.3) folgen.
- **F3.6 Nachweis-Anhang (M3):** Die gescannte, unterschriebene Teilnehmerliste
  wird **einmal** am Veranstaltungsticket (Elternticket) als Datei-Anhang
  hinterlegt und in **jedem** Kind-Ticket per Notiz referenziert (mit Link auf
  das Elternticket und Dateiname) — statt die Datei je Teilnehmer mehrfach zu
  speichern. Nutzt die Standard-MantisBT-Anhänge (`file_add`) und -Notizen
  (`bugnote_add`), keine zusätzliche Ablage. Upload-Feld auf der
  Teilnehmerseite (sichtbar, sobald die Kind-Tickets erzeugt sind);
  `qt_teilnehmer_attach_nachweis` in `core/QT_Participant.php`,
  `pages/veranstaltung_anhang.php`. **Schließt M3 ab** (F3.7 Terminvorschlag ist
  auf Version 1.1 zurückgestellt).
- **F3.5 Teilnehmerliste (M3):** Druckbare Anwesenheits- und Teilnehmerliste zu
  einer Veranstaltung mit Unterschriftenspalte, Maßnahmeninhalt und
  Rechtsgrundlage sowie Unterschriftszeilen für Ort/Datum und Unterweisende/n.
  Bewusst abhängigkeitsfrei umgesetzt als eigenständige, druckoptimierte
  HTML-Seite (*„Drucken → als PDF speichern"* im Browser) statt einer
  gebündelten PDF-Bibliothek. Aufruf über *Teilnehmer → Teilnehmerliste*
  (`pages/veranstaltung_teilnehmerliste.php`).
- **F3.4 Massenabschluss (M3):** Eine Veranstaltung wird für alle Teilnehmer in
  einem Schritt abgeschlossen. Über eine Anwesenheitsliste (Datum und
  Durchführende/r sind mit Termin bzw. Unterweisendem der Veranstaltung
  vorbelegt) werden die anwesenden Teilnehmer als *teilgenommen* markiert und
  ihre Nachweise über die bestehende Abschluss-Logik (F2.8) gültig gesetzt
  (`durchgefuehrt_am`, `gueltig_bis`, Durchführende/r, Ticketstatus, bei
  wiederkehrenden Maßnahmen inkl. Folgeticket). Abwesende werden *abwesend*
  markiert; ihr Nachweisticket bleibt offen und damit weiterhin fällig. Die
  Veranstaltung wird auf *durchgeführt* gesetzt. `qt_teilnehmer_complete_event`
  in `core/QT_Participant.php`; Auslösung über *Teilnehmer → Abschließen*.
- **F3.3 Kind-Tickets (M3):** Für eine Veranstaltung werden auf Knopfdruck je
  Teilnehmer die Nachweistickets als Kinder eines gemeinsamen
  Veranstaltungstickets erzeugt. Das Veranstaltungsticket (der „Sammeltermin")
  wird einmalig angelegt und in `qt_veranstaltung.eltern_bug_id` gespeichert;
  jedes Teilnehmerticket wird als Kind daruntergehängt (`depends on`). Der
  Grundsatz *„ein Ticket = eine Nachweis-Instanz"* bleibt gewahrt: hat eine
  Person aus der Kette (F2.3) bereits ein offenes Nachweisticket für die
  Maßnahme, wird dieses wiederverwendet und nur verknüpft, sonst neu erzeugt
  (mit `qt_nachweis`-Eintrag und Custom-Field-Belegung). Der Vorgang ist je
  Teilnehmer idempotent (gemerkte `bug_id`). `core/QT_Participant.php`
  (`qt_teilnehmer_generate_tickets`) und `core/QT_Event.php`
  (`qt_event_ensure_parent_ticket`); Auslösung über *Teilnehmer → Kind-Tickets
  erzeugen*. Grundlage für den Massenabschluss (F3.4).
- **F3.2 Teilnehmerauswahl (M3):** Zu einer Veranstaltung werden Teilnehmer aus
  dem Pool der *fälligen* Personen ausgewählt — Personen, die die Maßnahme der
  Veranstaltung laut Profil benötigen und für die kein gültiger Nachweis
  vorliegt. Der Pool ist nach Abteilung und Fälligkeit (kein Nachweis /
  in Bearbeitung / abgelaufen) filterbar; die Auswertung teilt sich die Logik
  mit der Soll-Ist-Prüfung (F2.5). Die Kapazität ist ein weiches Limit: eine
  Überbuchung wird gewarnt, nie blockiert. Neue Tabelle `qt_teilnehmer`, neues
  `core/QT_Participant.php` mit reinen, unit-getesteten Helfern
  (`qt_teilnehmer_capacity_state`, `qt_teilnehmer_status_valid`,
  `qt_teilnehmer_art_matches`) und idempotentem Hinzufügen. Erreichbar über
  *Veranstaltungen → Teilnehmer*. Grundlage für die Kind-Tickets (F3.3) und den
  Massenabschluss (F3.4).
- **F3.1 Veranstaltung anlegen (M3):** Verwaltung von Sammelterminen
  (Veranstaltungen) unter *Verwaltung → QualificationTracker → Veranstaltungen*:
  eine Maßnahme, an einem Termin für viele Personen gehalten, mit Ort,
  Unterweisendem, Kapazität und Status (geplant/durchgeführt/abgesagt).
  `core/QT_Event.php` mit reiner, unit-getesteter Validierung (`qt_event_validate`,
  `qt_event_valid_termin`) und CRUD; der Termin nimmt Datum oder Datum+Uhrzeit
  entgegen und wird normalisiert gespeichert. Grundlage für Teilnehmerauswahl
  (F3.2), Kind-Tickets (F3.3) und Massenabschluss (F3.4).
- **F2.1 Tätigkeitsprofile (M2):** Verwaltung von Profilen (benannte Mengen von
  Maßnahmen) unter *Verwaltung → QualificationTracker → Tätigkeitsprofile* mit
  Zuordnung der Maßnahmen (n:m). `core/QT_Profile.php` mit reiner, unit-getesteter
  Validierung und CRUD; Löschen ist blockiert, solange das Profil Personen
  zugeordnet ist. Grundlage für den Ketten-Generator (F2.3).
- **F2.2 Profilzuordnung:** Person ↔ Profil (n:m) mit Gültigkeitszeitraum unter
  *Verwaltung → QualificationTracker → Profilzuordnung*, mit Personenfilter.
  `core/QT_Assignment.php` mit reiner, unit-getesteter Validierung; historische
  (abgeschlossene) Zuordnungen bleiben erhalten, eine zweite *laufende* Zuordnung
  derselben Person zum selben Profil wird verhindert.
- **F2.3 Ketten-Generator:** Erzeugt aus den aktiven Profilzuordnungen einer
  Person die Nachweistickets — je Maßnahme ein offenes MantisBT-Ticket mit
  Custom-Field-Belegung, Vorgesetztem als Bearbeiter, Fälligkeit aus dem Rechner
  und `depends on`-Beziehungen aus den Vorbedingungen. Neue abgeleitete
  Index-Tabelle `qt_nachweis` (Idempotenz + Matrix-Performance), konfigurierbares
  Status-Mapping auf Standard-MantisBT-Status, Kategorien nach Maßnahmentyp.
  Vorschau + Erzeugung über *Profilzuordnung → Kette erzeugen*. Umsetzung der
  Entscheidungen G1–G5.
- **F2.5 Soll-Ist-Prüfung:** Read-only-Report unter *Verwaltung →
  QualificationTracker → Soll-Ist-Prüfung*: welche Person laut Profil eine
  Maßnahme benötigt, für die kein gültiger Nachweis existiert (kein Nachweis /
  in Bearbeitung / abgelaufen), inkl. Sonderfall „Beauftragung ohne gültige
  Qualifikation" (gültige Maßnahme, deren Vorbedingung nicht gültig ist).
  `core/QT_SollIst.php` mit reiner, unit-getesteter Bewertung; Abteilungsfilter.
- **F2.6 Dry-Run-Vorschau:** Betriebsweite Vorschau unter *Verwaltung →
  QualificationTracker → Ticketerzeugung*: welche Nachweistickets für alle (oder
  je Abteilung gefilterten) aktiven Personen entstünden, mit Sammel-Erzeugung
  („Alle Tickets erzeugen"). Ergänzt die Einzelperson-Vorschau aus F2.3
  (`qt_generator_plan_all` / `qt_generator_run_all`).
- **F2.4 Native Terminplanung:** Die Folgezyklus-Erzeugung ist vollständig nativ
  (`QT_DueDateCalculator` als einzige Fälligkeitsquelle, vorausschauend und
  ereignisgetrieben via F2.8) — **keine harte Plugin-Abhängigkeit**. Neue
  `core/QT_Integration.php` erkennt optionale Plugins (IssueRecurrence, Reveille)
  zur Laufzeit über `plugin_is_installed()` und degradiert sauber; der Status
  wird auf der Konfigurationsseite angezeigt. **Damit ist Meilenstein M2
  (Generator) vollständig.**
- **F2.8 Zwei Erzeugungsstrategien:** *Ereignisgetrieben* — beim Abschluss eines
  Nachweises (`core/QT_Completion.php`) werden `durchgefuehrt_am`/`gueltig_bis`/
  `durchfuehrender` gesetzt, der Nachweis gültig gestellt und für wiederkehrende
  Maßnahmen das Folgeticket erzeugt (`rollierend`/`extern`). *Vorausschauend* —
  Jahrgangserzeugung (`qt_generator_run_jahrgang`) legt die `kalenderjahr`/
  `stichmonat`-Tickets eines Jahres im Voraus an (Grundlage für Sammeltermine
  M3). Neue Seiten: offene Nachweise, Abschluss-Formular, Jahrgangs-Vorschau.
  Abschluss über Plugin-Aktion (Designentscheidung A), wiederverwendbar für den
  Massenabschluss (M3).
- **F2.7 Profiländerung:** „Profil abgleichen" je Person (Vorschau + Anwenden):
  nicht mehr benötigte Nachweise werden auf `entfallen` gesetzt und die Tickets
  geschlossen (mit Audit-Notiz), **bereits gültige Nachweise bleiben erhalten**,
  neu erforderliche Tickets werden erzeugt. Die Entscheidung „gültige behalten"
  ist als reine Funktion `qt_sync_obsolete_action` unit-getestet.
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
- **F1.5 Custom-Field-Bootstrap:** Die 13 Nachweis-Custom-Fields aus dem Konzept
  (§5) werden bei Installation angelegt (`core/QT_CustomFields.php`) und beim
  Speichern der Konfiguration mit dem Zielprojekt verknüpft. Bestehende
  gleichnamige Felder werden wiederverwendet statt dupliziert (Brücke aus der
  Bordmittel-Konfiguration). Statusanzeige je Feld auf der Konfigurationsseite.
  Datenschutz: kein Freitext-/Befundfeld.
- **F1.7 Beispiel-Maßnahmenkatalog:** Mitgelieferter Startkatalog
  (`files/beispielkatalog.yaml`, 11 Maßnahmen inkl. Vorbedingungsketten
  Hubarbeitsbühne/Flurförderzeuge) und Import über *Katalog → Beispielkatalog
  importieren* mit Vorschau. `core/QT_CatalogImport.php` enthält einen kleinen,
  abhängigkeitsfreien YAML-Leser (unit-getestet) und nutzt die vorhandene
  Validierung und Vorbedingungslogik; bestehende Maßnahmen werden übersprungen
  oder wahlweise überschrieben. **Damit ist Meilenstein M1 (Fundament)
  vollständig.**

### Behoben
- Direktaufruf-Schutz der Hauptklasse prüfte die nicht existente Konstante
  `MANTIS_DIR` und verhinderte dadurch das Laden im Plugin-Manager; jetzt
  korrekt `MANTIS_VERSION` (wie im Schwester-Plugin Reveille).

[Unreleased]: https://github.com/marcwoge/QualificationTracker/commits/main
