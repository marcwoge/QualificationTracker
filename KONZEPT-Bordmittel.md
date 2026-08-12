# Qualifikations- und Unterweisungsmanagement in MantisBT
## Teil 1: Umsetzung mit Bordmitteln (ohne Eigenentwicklung)

**Stand:** 2026-08-12
**Zielgröße:** > 200 Mitarbeiter, ca. 6–10 überwachungspflichtige Maßnahmen je Person
**Erwartetes Volumen:** 1.600–2.500 aktive Tickets pro Jahr, ca. 12.000–15.000 Tickets nach 5 Jahren Betrieb

---

## 1. Grundprinzip

> **Ein Ticket = eine Nachweis-Instanz: (Person × Maßnahme × Gültigkeitszyklus)**

Jedes Ticket ist damit ein Dokument mit Lebenszyklus, Fälligkeitsdatum, Anhang (unterschriebener Nachweis) und lückenloser Änderungshistorie. Die Historie ist der eigentliche Wert im Audit nach ISO 45001 / ISO 9001 — sie belegt nicht nur *dass*, sondern *wann von wem* dokumentiert wurde.

### Warum nicht anders modellieren?

| Verworfener Ansatz | Problem |
|---|---|
| Ein Ticket je Mitarbeiter | Kein Fälligkeitsdatum pro Maßnahme möglich, keine Wiederholung, Historie vermischt |
| Ein Ticket je Maßnahme (alle Personen darin) | Kein individueller Nachweis, kein Ausscheiden Einzelner, Bulk-Status unbrauchbar |
| Ein Ticket je Maßnahme, Person als Custom Field, Zyklus als Notiz | Fälligkeit nicht filterbar, keine Wiederholungsmechanik, Historie unbrauchbar |

---

## 2. Vier Maßnahmentypen und ihre Mechanik

| Typ | Kürzel | Beispiel | Zeitverhalten | Mechanik |
|---|---|---|---|---|
| Qualifikation, unbefristet | `QU` | Bedienerschulung Hubarbeitsbühne (DGUV Grundsatz 308-008) | einmalig | Stammnachweis-Ticket, Status `gültig`, Zertifikat als Anhang |
| Qualifikation, befristet | `QB` | Ersthelfer (2 Jahre), Elektrofachkraft-Auffrischung | ablaufend | Ticket zurückgestellt → **Reveille** reaktiviert zum Ablauf minus Vorlauf |
| Unterweisung | `UW` | Jahresunterweisung, Brandschutz, Gefahrstoffe | jährlich (Jugendliche: halbjährlich) | **IssueRecurrence** erzeugt je Zyklus ein neues Ticket |
| Beauftragung | `BE` | Schriftliche Beauftragung nach DGUV Vorschrift 1 § 7 | dauerhaft, widerrufbar | Ticket bleibt offen = „Beauftragung aktiv"; Schließen = Widerruf/Austritt |
| (Vorsorge) | `VO` | G-Untersuchung, Eignungsuntersuchung | befristet | wie `QB`, **aber in separatem Projekt** — siehe Abschnitt 9 |

### Verkettung am Beispiel Hubarbeitsbühne

```
#1001  QU  Bedienerschulung Hubarbeitsbühne – M. Mustermann          [gültig]
             ↑ abhängig von
#1002  BE  Beauftragung Bedienung Hubarbeitsbühne – M. Mustermann    [aktiv]
             ↑ abhängig von
#1003  UW  Jahresunterweisung Hubarbeitsbühne 2026 – M. Mustermann   [fällig 30.04.2026]
             → Recurrence-Regel: jährlich, erzeugt 2027, 2028, …
```

Der Nutzen der Verkettung: Wird #1002 widerrufen oder ist #1001 nicht belegbar, ist über die Beziehungsanzeige sofort sichtbar, dass die Unterweisung #1003 rechtlich ins Leere läuft. Eine Excel-Ablaufliste kann diese Abhängigkeit nicht abbilden.

---

## 2a. Fälligkeitsmodell — je Maßnahme, nicht global

Die Frage „kalenderjährlich oder rollierend" hat keine betriebsweit einheitliche Antwort. Sie wird **je Maßnahme** entschieden:

| Modus | Berechnung `gueltig_bis` | Typische Maßnahmen |
|---|---|---|
| `rollierend` | `durchgefuehrt_am + intervall_monate` | interne Schulungen ohne externen Träger |
| `kalenderjahr` | 31.12. des Zieljahres | Jahresunterweisung, Brandschutz, Gefahrstoffe |
| `stichmonat` | Ende Monat X im Zieljahr | wie `kalenderjahr`, aber abteilungsweise gestaffelt |
| `extern` | keine Berechnung — Datum wird aus dem Nachweis übernommen | Ersthelfer, arbeitsmedizinische Vorsorge, Kranführer |

**`extern` ist kein Sonderfall, sondern häufig.** Bei einer Ersthelfer-Bescheinigung oder einer Vorsorgebescheinigung setzt der Ausbildungsträger beziehungsweise der Betriebsarzt das Datum. Rechnet das System selbst, weicht der Systemstand vom Dokument ab — und im Audit gilt das Dokument.

### Vorwärtsdrift bei `rollierend`

Findet eine Unterweisung sechs Wochen vor Fälligkeit statt, weil der Termin gerade passt, rutscht der Folgezyklus um sechs Wochen nach vorn. Über mehrere Jahre wird aus dem Jahres- ein Zehnmonatsintervall, und irgendwann liegen zwei Unterweisungen im selben Kalenderjahr — betrieblich teuer, ohne dass es jemand beschlossen hätte.

**Gegenmaßnahme:** Karenzzeit mit Ankererhalt (Prinzip wie bei der Hauptuntersuchung). Wird innerhalb von *n* Tagen vor dem Soll-Termin durchgeführt, wird der Folgetermin vom **Soll**-Datum aus berechnet, nicht vom Ist-Datum. Erst außerhalb der Karenz verschiebt sich der Anker.

Dafür wird ein zusätzliches Feld benötigt:

| Feldname | Typ | Zweck |
|---|---|---|
| `soll_termin` | Datum | Ankerdatum des Zyklus; Berechnungsbasis für den Folgezyklus |

Ohne dieses Feld lässt sich der Anker im Nachhinein nicht rekonstruieren, weil das Durchführungsdatum die ursprüngliche Sollfrist nicht mehr enthält.

### Konsequenz für die Bordmittel-Umsetzung

Die Ticketerzeugung funktioniert in den beiden Grundmodi unterschiedlich:

- **`kalenderjahr` / `stichmonat`** sind vorausschauend planbar. Alle Tickets des Folgejahres können im Dezember gesammelt erzeugt werden — Voraussetzung dafür, dass die Terminplanung für Sammelunterweisungen (Abschnitt 8) überhaupt funktioniert.
- **`rollierend`** funktioniert nur ereignisgetrieben: Das Folgeticket kann erst beim Abschluss des vorherigen entstehen, weil vorher das Fälligkeitsdatum unbekannt ist.

Mit IssueRecurrence allein lässt sich sauber nur der erste Fall abbilden, da die Wiederholungsregel einen festen Rhythmus beschreibt. Für `rollierend` und `extern` bleibt mit Bordmitteln Handarbeit: Beim Abschluss eines Tickets wird `gueltig_bis` gesetzt und das Folgeticket manuell angelegt.

**Pragmatische Empfehlung für den Start:** Alle betrieblich frei terminierbaren Unterweisungen auf `kalenderjahr` beziehungsweise `stichmonat` legen (abteilungsweise gestaffelt, um die Dezember-Lawine zu vermeiden), alle extern datierten Nachweise auf `extern` mit manueller Pflege. `rollierend` erst mit dem Plugin einführen.

---

## 3. Projektstruktur

```
Qualifikationsmanagement (Elternprojekt, nur Lesezugriff für Führungskräfte)
├── QM Standort Hamburg
│   ├── QM Werkstatt
│   ├── QM Service / Außendienst
│   └── QM Verwaltung
├── QM Standort <weitere>
└── Arbeitsmedizinische Vorsorge   ← eigene Sichtbarkeit, siehe Abschnitt 9
```

**Begründung bei > 200 Mitarbeitern:** Die Unterprojekte sind primär ein *Berechtigungs*- und kein Ordnungsinstrument. Ein Meister sieht nur seine Abteilung, die Fachkraft für Arbeitssicherheit sieht das Elternprojekt und damit alles. Gleichzeitig halbiert die Vorfilterung die Ticketlisten, die sonst bei dieser Größe unbedienbar werden.

---

## 4. Kategorien

Kategorien bilden den Maßnahmentyp ab (nicht die einzelne Maßnahme — dafür ist der Maßnahmenschlüssel da):

- `Unterweisung`
- `Qualifikation`
- `Beauftragung`
- `Vorsorge`
- `Stammdaten` (Personenanlage, Austritt, Rollenwechsel)

---

## 5. Custom Fields

| Feldname | Typ | Pflicht | Zweck |
|---|---|---|---|
| `mitarbeiter` | Liste (aus Personalstamm gepflegt) | ja | Betroffene Person — bewusst **nicht** der Mantis-Benutzer |
| `personalnummer` | String | ja | Join-Schlüssel für Elasticsearch und HR-Abgleich |
| `massnahmenschluessel` | Liste | ja | z. B. `UW-HAB`, `QU-HAB-308-008`, `BE-HAB` — Achse der späteren Matrix |
| `rechtsgrundlage` | String | nein | „DGUV Vorschrift 1 § 4, ArbSchG § 12" |
| `durchgefuehrt_am` | Datum | bei Abschluss | Beginn des Gültigkeitszeitraums |
| `gueltig_bis` | Datum | bei Abschluss | Ende des Gültigkeitszeitraums, treibt Ampel und Reveille |
| `intervall_monate` | Zahl | ja bei `UW`/`QB` | 12 (Standard), 6 (Jugendliche), 24 (Ersthelfer) |
| `faelligkeitsmodus` | Liste | ja | `rollierend` / `kalenderjahr` / `stichmonat` / `extern` — siehe Abschnitt 2a |
| `soll_termin` | Datum | ja bei `UW` | Ankerdatum des Zyklus, verhindert Vorwärtsdrift |
| `durchfuehrender` | String | bei Abschluss | Unterweisender / Ausbildungsstelle |
| `nachweisart` | Liste | ja | Unterschriftenliste / Zertifikat / Beauftragungsschreiben / Teilnahmebestätigung |
| `abteilung` | Liste | ja | Redundant zum Unterprojekt, aber nötig für die Auswertung über Projektgrenzen |
| `veranstaltung_id` | String | nein | Klammer für Sammeltermine (siehe Abschnitt 8) |

**Wichtig:** `mitarbeiter` und `personalnummer` sind bewusst redundant. Die Liste ist für Menschen, die Nummer für Maschinen — bei Namensgleichheit (bei 200+ Personen realistisch) rettet dich die Nummer.

### Zuständigkeit

Das Mantis-Feld **Zugewiesen an** bekommt den *Vorgesetzten*, der die Durchführung schuldet — nicht den Mitarbeiter. Grund: Die meisten gewerblichen Mitarbeiter haben keinen Mantis-Account, und Erinnerungsmails müssen an einen handlungsfähigen Empfänger gehen.

---

## 6. Statusmodell

Eigene Statuswerte über `$g_status_enum_string`. Vorschlag:

| Wert | Status | Bedeutung |
|---|---|---|
| 10 | `offen` | Bedarf erkannt, noch nichts veranlasst |
| 20 | `geplant` | Termin steht, Person eingeladen |
| 40 | `durchgeführt` | Maßnahme erfolgt, Nachweis noch nicht abgelegt |
| 50 | `nachweis abgelegt` | Anhang vorhanden, noch nicht freigegeben |
| 80 | `gültig` | Freigegeben, `gueltig_bis` gesetzt — der Normalzustand |
| 85 | `zurückgestellt` | Reveille wacht auf das Ablaufdatum |
| 90 | `abgelaufen` | Frist überschritten, Handlungsbedarf |
| 95 | `entfallen` | Person ausgeschieden / Beauftragung widerrufen / Tätigkeit entfällt |

Farbzuordnung in `$g_status_colors`: 90 rot, 10/20 gelb, 80 grün, 95 grau.

**Fälligkeitsdatum aktivieren** (`$g_due_date_view_threshold`, `$g_due_date_update_threshold`). Damit bekommst du ohne jede Entwicklung: Rot-Hervorhebung überfälliger Zeilen, den Filter „überfällig", und die eingebauten Erinnerungsmails.

---

## 7. Gespeicherte Filter — das Cockpit

Bei 2.000+ Tickets im Jahr ist die Filterkonfiguration **kein Nebenschauplatz, sondern die eigentliche Bedienoberfläche**. Diese Filter als öffentlich gespeicherte Filter anlegen:

| Filtername | Kriterium | Zielgruppe |
|---|---|---|
| `A – Überfällig` | Fälligkeit < heute, Status < 80 | SiFa, Geschäftsführung |
| `B – Fällig 30 Tage` | Fälligkeit < heute+30, Status < 80 | Meister |
| `C – Fällig 90 Tage` | Fälligkeit < heute+90, Status < 80 | Planung Schulungstermine |
| `D – Ohne Nachweis` | Status = 40, kein Anhang | Sachbearbeitung |
| `E – Beauftragung ohne Qualifikation` | Kategorie = Beauftragung, Beziehung fehlt | SiFa, Audit-Vorbereitung |
| `F – Meine Abteilung offen` | Zugewiesen an = ich, Status < 80 | Führungskräfte |
| `G – Neueintritte ohne Erstunterweisung` | Erstellt < 14 Tage, `UW-ERST`, Status < 80 | Personal |

**Standardfilter beim Login** auf `F` setzen. Ohne das landet jede Führungskraft in einer 2.000-Zeilen-Liste und benutzt das System nach zwei Wochen nicht mehr.

---

## 8. Sammeltermine (kritisch ab 50 Mitarbeitern)

Eine Jahresunterweisung findet nicht 200-mal einzeln statt, sondern als 12 Veranstaltungen mit je 15–25 Teilnehmern. Mit Bordmitteln:

1. Elternticket anlegen: „Jahresunterweisung Werkstatt – Termin 12.03.2026", `veranstaltung_id` = `UW-2026-03-12-WST`
2. Die Teilnehmer-Tickets über die Beziehung **Kind von** anhängen
3. Nach dem Termin: Massenbearbeitung (Filter auf `veranstaltung_id`) → Status `durchgeführt`, `durchgefuehrt_am` und `gueltig_bis` in einem Rutsch setzen
4. Unterschriebene Teilnehmerliste als PDF an das *Elternticket* anhängen, in den Kindern per Notiz auf die Elternnummer verweisen

**Grenze mit Bordmitteln:** Die Massenbearbeitung in Mantis kann Custom Fields nicht in allen Versionen zuverlässig setzen. Das ist der Punkt, an dem die Handarbeit bei > 200 Mitarbeitern spürbar wird — und das stärkste einzelne Argument für das Plugin (siehe ROADMAP, Meilenstein M3).

---

## 9. Datenschutz — arbeitsmedizinische Vorsorge

Arbeitsmedizinische Vorsorgedaten sind Gesundheitsdaten nach Art. 9 DSGVO und gehören **nicht** in dasselbe Projekt wie der Rest.

- Eigenes Projekt `Arbeitsmedizinische Vorsorge`, Sichtbarkeit ausschließlich für Personalstelle und SiFa
- Es wird **ausschließlich** gespeichert: Art der Vorsorge, Datum, „Nachuntersuchung bis". **Keine** Befunde, keine Diagnosen, keine Eignungsbeurteilung im Klartext
- Der Betriebsarzt übermittelt ohnehin nur die Vorsorgebescheinigung — mehr darf auch gar nicht im System landen
- Löschkonzept: Aufbewahrungsfristen je Vorsorgeart definieren (bei Gefahrstoffexposition teils Jahrzehnte), Löschung dokumentieren

Diesen Punkt vor Inbetriebnahme mit dem Datenschutzbeauftragten abstimmen und im Verarbeitungsverzeichnis eintragen.

---

## 10. Auswertung: Qualifikationsmatrix über Elasticsearch

Was MantisBT nativ nicht kann, ist die Matrix Person × Maßnahme mit Ampelfarben. Über den vorhandenen Stack ist das aber unkritisch:

```
MySQL (mantis_bug_table, mantis_custom_field_string_table,
       mantis_bug_relationship_table)
   → NiFi (QueryDatabaseTable, inkrementell auf last_updated)
   → Elasticsearch (Index qm-nachweise, ein Dokument je Ticket, flach denormalisiert)
   → Kibana
```

**Kibana-Visualisierungen:**
- *Heatmap*: X = `massnahmenschluessel`, Y = `personalnummer`, Farbe = Restgültigkeit in Tagen
- *Gauge* je Abteilung: Anteil Status `gültig` an allen Soll-Maßnahmen
- *Data Table* „Top 20 Überfällige" für die monatliche ASA-Sitzung
- *Time Series*: Erfüllungsgrad über die Zeit — die Kurve, die im Audit Wirksamkeit belegt

**Scripted Field** für die Ampel:
```painless
(doc['gueltig_bis'].value.toInstant().toEpochMilli() - new Date().getTime())
  / 86400000
```
→ Bänder: < 0 rot, < 30 orange, < 90 gelb, sonst grün.

**Wichtig:** Die Auswertung läuft ausschließlich auf Elasticsearch, **nicht** über SQL-Views auf der Produktivdatenbank. Der Join über `mantis_custom_field_string_table` ist bei 15.000 Tickets × 11 Feldern (165.000 Zeilen) für Ad-hoc-Abfragen zu langsam und würde die Mantis-Performance für alle Benutzer beeinträchtigen.

---

## 11. Ersterfassung und Stammdatenpflege

**Ersterfassung** ist das unterschätzte Arbeitspaket. Bei 200 Mitarbeitern × 8 Maßnahmen entstehen 1.600 Tickets, die einmal befüllt werden müssen.

Weg: Excel/CSV mit Spalten `personalnummer;name;abteilung;massnahmenschluessel;durchgefuehrt_am;gueltig_bis` → NiFi (`GetFile` → `SplitRecord` → `InvokeHTTP` auf die Mantis-REST-API `POST /api/rest/issues`) → Tickets. Realistisch ein Arbeitstag inklusive Test, gegenüber mehreren Wochen Handarbeit.

**Laufender Personalstamm:** Wöchentlicher NiFi-Lauf gegen die HR-Quelle:
- Neueintritt → Ticketkette gemäß Tätigkeitsprofil anlegen, Erstunterweisung mit Fälligkeit +14 Tage
- Austritt → alle offenen Tickets der Person auf `entfallen`
- Abteilungswechsel → Zuständigkeit umhängen, ggf. neue Maßnahmen anlegen

---

## 12. Erinnerungs- und Eskalationsstufen

Mantis-Bordmittel (E-Mail-Benachrichtigungen + Fälligkeitserinnerung) decken Stufe 1 ab; Stufe 2 und 3 brauchen einen Cron-Job oder das Plugin.

| Stufe | Zeitpunkt | Empfänger | Bordmittel? |
|---|---|---|---|
| 1 | 90 Tage vor Ablauf | Vorgesetzter | ja (Fälligkeitserinnerung) |
| 2 | 30 Tage vor Ablauf | Vorgesetzter + SiFa | Cron + Skript |
| 3 | bei Überschreitung | Abteilungsleitung + SiFa | Cron + Skript |
| 4 | 30 Tage überfällig | Geschäftsführung | Cron + Skript |

Bei sicherheitsrelevanten Beauftragungen (Hubarbeitsbühne, Stapler, Kran) sollte Stufe 3 zusätzlich einen Vermerk „Beauftragung ruht bis Nachholung" im Ticket erzeugen. Das ist der Punkt, an dem das System vom Erinnerungs- zum Steuerungswerkzeug wird.

---

## 13. Grenzen dieses Ansatzes — ehrlich benannt

| Grenze | Auswirkung | Abhilfe |
|---|---|---|
| Keine Matrix-Ansicht in Mantis | Auswertung nur über Kibana | Plugin M4 |
| Massenbearbeitung setzt Custom Fields unzuverlässig | Sammeltermine bleiben Handarbeit | Plugin M3 |
| Ticketketten müssen manuell angelegt werden | ~15 Min. je Neueintritt | Plugin M2 |
| Keine qualifizierte elektronische Signatur | Papier-Unterschriftenliste bleibt der Nachweis | rechtlich ausreichend, im Audit erläutern |
| Keine Soll-Ist-Prüfung („wer *müsste* was haben?") | Fehlende Maßnahmen fallen nicht auf | Plugin M2 (Tätigkeitsprofile) |
| Ticketlisten werden bei > 200 MA unübersichtlich | Akzeptanzrisiko | Filter-Set aus Abschnitt 7 zwingend |

### Ist MantisBT überhaupt das richtige Werkzeug?

Es gibt spezialisierte Qualifikationsmatrix-Module in QM- und HR-Software mit fertiger Matrix-UI und Rechtskataster-Pflege. Deren Vorteile sind die sofortige Verfügbarkeit und die gepflegten Maßnahmenkataloge; deren Nachteile sind laufende Lizenzkosten, ein weiteres Datensilo und Fremdhosting personenbezogener Daten.

Für NTC spricht für MantisBT: Der Stack (Mantis, NiFi, Elasticsearch, Kibana) läuft bereits, die Wiederholmechanik existiert als eigene Plugins, die Daten bleiben im Haus, und die Historie ist auditfest. Dagegen spricht: Es ist eine Zweckentfremdung, und die Matrix-Ansicht sowie die Soll-Ist-Prüfung sind Eigenentwicklung. Bei > 200 Mitarbeitern lohnt sich diese Entwicklung — bei 30 Mitarbeitern wäre die Antwort vermutlich anders.

---

## 14. Empfohlene Einführungsreihenfolge

1. **Woche 1–2:** Maßnahmenkatalog definieren (welche Maßnahme, welches Intervall, welche Rechtsgrundlage, welche Tätigkeit löst sie aus). Das ist fachliche Arbeit der SiFa, keine IT-Arbeit — und der eigentliche Engpass.
2. **Woche 3:** Mantis konfigurieren (Projekte, Kategorien, Custom Fields, Status, Filter).
3. **Woche 4:** Pilot mit einer Abteilung (20–30 Personen), vollständig manuell.
4. **Woche 5–6:** Ersterfassung per NiFi für alle Abteilungen, Recurrence-Regeln aufsetzen.
5. **Woche 7:** Elasticsearch-Anbindung und Kibana-Dashboard.
6. **Ab Woche 8:** Regelbetrieb; parallel Plugin-Entwicklung gemäß ROADMAP.

Der Pilot in Schritt 3 ist nicht verhandelbar. Er kostet zwei Wochen und verhindert, dass ein Modellierungsfehler in 1.600 Tickets festgeschrieben wird.
