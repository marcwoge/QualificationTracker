# QualificationTracker

**Unterweisungen und Qualifikationen verwalten — in dem System, das Sie schon betreiben.**

Ein MantisBT-Plugin für Unternehmen mit gesetzlichen Unterweisungspflichten nach ArbSchG § 12 und DGUV Vorschrift 1 § 4.

---

## Das Problem

In den meisten Betrieben lebt die Qualifikationsmatrix in einer Excel-Datei. Sie funktioniert, solange eine Person sie pflegt und alle Termine im Kopf hat. Sie versagt an drei Stellen zuverlässig:

**Sie erinnert nicht.** Eine Tabelle meldet sich nicht, wenn die Jahresunterweisung überfällig ist. Jemand muss sie öffnen und lesen — und tut das erfahrungsgemäß, wenn das Audit angekündigt wird.

**Sie kennt keine Abhängigkeiten.** Dass die Beauftragung zum Führen einer Hubarbeitsbühne wertlos ist, wenn die zugrunde liegende Bedienerschulung nicht belegbar ist, steht in keiner Spalte. Es fällt auf, wenn etwas passiert ist.

**Sie beweist nichts.** Im Auditgespräch — oder schlimmer, im Haftungsfall — ist die entscheidende Frage nicht, was in der Tabelle steht, sondern wann es dort hineingeschrieben wurde und von wem. Eine Excel-Zelle beantwortet das nicht.

## Der Ansatz

QualificationTracker bildet jeden Nachweis als MantisBT-Ticket ab: eine Person, eine Maßnahme, ein Gültigkeitszeitraum. Damit erbt jeder Unterweisungsnachweis, was ein Issue-Tracker seit jeher kann — Fälligkeiten, Zuständigkeiten, Benachrichtigungen, Anhänge und eine lückenlose, nicht nachträglich veränderbare Historie.

Das Plugin ergänzt darum die fachliche Logik, die ein Bug-Tracker nicht mitbringt: Maßnahmenkatalog, Tätigkeitsprofile, automatische Ticketketten, Sammeltermine, Ampel-Matrix und gestufte Eskalation.

## Warum das für viele Betriebe die richtige Wahl ist

| | |
|---|---|
| **Keine Lizenzkosten** | Freie Software unter MIT-Lizenz. Kein Nutzerpreis, keine Staffelung, keine Jahresgebühr. |
| **Keine Daten außer Haus** | Alles bleibt auf Ihrem Server. Kein Cloud-Vertrag, keine Auftragsverarbeitung, kein Drittlandtransfer. |
| **Kein weiteres Silo** | Kein zusätzlicher Login, keine zusätzliche Schulung, kein weiteres System im Wartungsplan. |
| **Auditfeste Historie** | Jede Änderung mit Zeitstempel und Urheber — genau das, was ISO 45001 und ISO 9001 sehen wollen. |
| **Offene Schnittstellen** | REST-Endpunkte für Auswertung in Elasticsearch, Power BI oder was auch immer Sie einsetzen. |

## Für wen es sich nicht lohnt

Ehrlichkeit spart beiden Seiten Zeit:

- **Unter etwa 50 Mitarbeitern** ist der Einrichtungsaufwand höher als der Nutzen. Eine gepflegte Tabelle mit Kalendererinnerungen tut es dann auch.
- **Ohne bestehende MantisBT-Installation** ist der Weg über eine spezialisierte QM-Software meist kürzer.
- **Wer ein gepflegtes Rechtskataster erwartet**, ist hier falsch: Welche Maßnahmen Ihr Betrieb braucht, entscheidet Ihre Fachkraft für Arbeitssicherheit, nicht dieses Plugin.
- **Wer digitale Unterschriften braucht**, muss zusätzlich investieren. Der Rechtsnachweis bleibt hier die unterschriebene Teilnehmerliste als Anhang.

---

## Status

🔵 **In Konzeption.** Das Datenmodell und der Funktionsumfang stehen (siehe [ROADMAP.md](ROADMAP.md)), die Implementierung hat noch nicht begonnen.

Wer heute schon starten will: Das Konzept [KONZEPT-Bordmittel.md](KONZEPT-Bordmittel.md) beschreibt, wie sich der Kern **ohne dieses Plugin** allein durch Konfiguration von MantisBT abbilden lässt. Das Plugin automatisiert später, was dort Handarbeit bleibt. Ein Migrationspfad von der Bordmittel-Konfiguration in die Plugin-Datenstruktur ist vorgesehen (F8.6).

---

## Voraussetzungen

| Komponente | Version | Anmerkung |
|---|---|---|
| MantisBT | 2.25 oder neuer | |
| PHP | 8.1 oder neuer | |
| MySQL / MariaDB | 8.0 / 10.6 oder neuer | |
| weitere Plugins | — | **keine erforderlich.** Terminplanung und Ablaufüberwachung sind eingebaut. Ist [Reveille](https://github.com/marcwoge/mantisBT-reveille) installiert, wird es für die Ablaufreaktivierung automatisch mitgenutzt |
| Cron oder systemd-Timer | | für Ablaufwächter und Eskalation |

---

## Installation

```bash
cd /var/www/mantisbt/plugins
git clone https://github.com/marcwoge/QualificationTracker.git QualificationTracker
chown -R www-data:www-data QualificationTracker
```

Anschließend in MantisBT: **Verwaltung → Plugins verwalten → QualificationTracker → Installieren**.

Das Plugin legt seine Tabellen (Präfix `qt_`) sowie die benötigten Custom Fields selbstständig an.

### Cron einrichten

```bash
# /etc/cron.d/qualificationtracker
30 5 * * *  www-data  php /var/www/mantisbt/plugins/QualificationTracker/scripts/qt_cron.php >> /var/log/mantis/qt_cron.log 2>&1
```

Der Lauf setzt abgelaufene Nachweise, reaktiviert zurückgestellte Tickets und versendet die Eskalationsstufen. Er ist idempotent und kann gefahrlos mehrfach am Tag laufen.

---

## Erste Schritte

1. **Projekt anlegen.** Ein Projekt `Qualifikationsmanagement`, darunter Unterprojekte je Abteilung — sie steuern, wer was sieht.
2. **Maßnahmenkatalog importieren.** Unter *Verwaltung → QualificationTracker → Katalog* den mitgelieferten Beispielkatalog laden und auf Ihren Betrieb anpassen. **Dieser Schritt ist der eigentliche Aufwand** und gehört in die Hände Ihrer Fachkraft für Arbeitssicherheit, nicht in die IT.
3. **Personen importieren.** CSV mit Personalnummer, Name, Abteilung und Vorgesetztem.
4. **Tätigkeitsprofile definieren.** Welche Rolle zieht welche Maßnahmen nach sich?
5. **Profile zuordnen und Vorschau prüfen.** Vor der Erzeugung zeigt der Dry-Run, welche Tickets entstehen würden.
6. **Bestandsnachweise importieren.** Historische Durchführungs- und Ablaufdaten, damit nicht alles am Tag eins fällig wird.

**Empfehlung:** Beginnen Sie mit einer Abteilung und drei Maßnahmen. Ein Modellierungsfehler, der in 1.600 Tickets festgeschrieben ist, kostet erheblich mehr als zwei Wochen Pilotbetrieb.

---

## Betrieb und Wartung

### Tägliche Automatik

Der Cron-Lauf protokolliert jeden Durchgang; das Protokoll ist unter *Verwaltung → QualificationTracker → Laufprotokoll* einsehbar. Bleibt es mehrere Tage leer, läuft der Cron nicht — das ist der wichtigste einzelne Überwachungspunkt der Installation.

### Datensicherung

Es genügt die reguläre MantisBT-Sicherung (Datenbank plus Verzeichnis `uploads`). Das Plugin legt keine Daten außerhalb dieser beiden Orte ab. Prüfen Sie, dass die Anhänge wirklich mitgesichert werden — bei Nachweisdokumenten sind sie der eigentliche Wert.

### Upgrade

```bash
cd /var/www/mantisbt/plugins/QualificationTracker
git pull
```

Danach in MantisBT die Plugin-Verwaltung öffnen; nötige Schema-Migrationen werden angeboten. **Vor jedem Upgrade eine Datenbanksicherung anlegen.**

### Wiederkehrende Pflegeaufgaben

| Intervall | Aufgabe |
|---|---|
| wöchentlich | Personalstamm abgleichen (Ein- und Austritte) |
| monatlich | Report „Beauftragung ohne gültige Qualifikation" prüfen |
| jährlich | Maßnahmenkatalog gegen geänderte Vorschriften prüfen |
| jährlich | Löschvorschlagsliste abarbeiten (Aufbewahrungsfristen) |

### Auswertung anbinden

Die REST-Endpunkte liefern den Nachweisbestand denormalisiert:

```
GET /api/rest/plugins/QualificationTracker/nachweise?status=abgelaufen&limit=1000&offset=0
```

Im Referenzaufbau zieht Apache NiFi diese Daten periodisch (seitenweise über `limit`/`offset`) nach Elasticsearch; Kibana rendert daraus die Ampel-Heatmap Person × Maßnahme sowie den Erfüllungsgrad je Abteilung über die Zeit. Details in [KONZEPT-Bordmittel.md](KONZEPT-Bordmittel.md), Abschnitt 10, und im [Administratorhandbuch](docs/Administratorhandbuch.md).

---

## Datenschutz

Das Plugin verarbeitet personenbezogene Daten. Vor Inbetriebnahme zu klären:

- Eintrag im Verzeichnis von Verarbeitungstätigkeiten — Vorlagentext unter [docs/Verarbeitungsverzeichnis.md](docs/Verarbeitungsverzeichnis.md); die Verwaltungsseite „Verarbeitungsverzeichnis" zeigt zusätzlich eine konfigurationsbezogene Fassung mit Druck-/PDF-Ansicht
- Beteiligung des Betriebsrats prüfen — die Auswertung von Qualifikationsdaten kann der Mitbestimmung nach BetrVG § 87 unterliegen
- Arbeitsmedizinische Vorsorge gehört in ein **separates Projekt** mit eigener Sichtbarkeit. Es werden ausschließlich Art, Datum und Nachuntersuchungsfrist geführt — **keine Befunde**
- Löschfristen je Maßnahmenart festlegen; bei Gefahrstoffexposition gelten teils jahrzehntelange Aufbewahrungspflichten

---

## Mitwirken

Fehlermeldungen und Verbesserungsvorschläge über die Issues dieses Repositories. Pull Requests bitte gegen `main`, mit Bezug auf die Funktions-ID aus der [ROADMAP.md](ROADMAP.md).

## Lizenz

**MIT** — siehe [LICENSE](LICENSE). Frei einsetzbar, veränderbar und weitergebbar, auch kommerziell.

## Verwandte Plugins

Aus derselben Reihe, ebenfalls unter MIT:

- [Reveille](https://github.com/marcwoge/mantisBT-reveille) — Tickets schlafen legen und zum richtigen Zeitpunkt wecken
- [Reminder](https://github.com/marcwoge/mantisBT-Reminder) — Erinnerung an offene Tickets
- [LinkedIssueFactory](https://github.com/marcwoge/mantisBT-LinkedIssueFactory) — verknüpfte Folgetickets, einmalig oder wiederkehrend
- [IssueRecurrence](https://github.com/marcwoge/mantisBT-IssueRecurrence) — Serien-Tickets aus Vorlagen
- [FullTextSearch](https://github.com/marcwoge/mantisbt-fulltextsearch) — Volltextsuche über alle Ticketfelder

## Autor

Marc-Philipp Woge
