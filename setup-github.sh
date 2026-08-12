#!/usr/bin/env bash
#
# setup-github.sh — legt das Repository QualificationTracker an und befüllt es
# mit Labels, Meilensteinen und Issues gemäß ROADMAP.md.
#
# Voraussetzung: GitHub CLI installiert und authentifiziert
#   gh auth login
#
# Aufruf (im Verzeichnis mit README.md, ROADMAP.md, KONZEPT-Bordmittel.md):
#   chmod +x setup-github.sh && ./setup-github.sh
#
set -euo pipefail

REPO="marcwoge/QualificationTracker"
DESC="MantisBT-Plugin für Unterweisungs- und Qualifikationsmanagement nach ArbSchG/DGUV"

# ---------------------------------------------------------------- Repository
echo "==> Repository anlegen (oeffentlich - freie Software)"
gh repo create "$REPO" --public --description "$DESC" || echo "    existiert bereits, weiter"
gh repo edit "$REPO" --add-topic mantisbt --add-topic mantisbt-plugin --add-topic arbeitssicherheit 2>/dev/null || true

if [ ! -d .git ]; then
  git init -b main
  git remote add origin "https://github.com/${REPO}.git"
fi

cat > .gitignore <<'EOF'
/vendor/
/node_modules/
*.log
.DS_Store
.idea/
.vscode/
EOF

cat > LICENSE <<'EOF'
MIT License

Copyright (c) 2026 Marc-Philipp Woge

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
EOF

git add -A
git commit -m "Konzept, Roadmap und README für QualificationTracker" || true
git push -u origin main

# -------------------------------------------------------------------- Labels
echo "==> Labels anlegen"
add_label() { gh label create "$1" --color "$2" --description "$3" --repo "$REPO" 2>/dev/null || \
              gh label edit  "$1" --color "$2" --description "$3" --repo "$REPO"; }

add_label "typ:feature"     "1d76db" "Neue Funktionalität"
add_label "typ:doku"        "0e8a16" "Dokumentation"
add_label "typ:infra"       "5319e7" "Build, Test, Paketierung"
add_label "typ:entscheidung" "d93f0b" "Fachliche Klärung nötig"
add_label "bereich:daten"   "fbca04" "Datenmodell und Persistenz"
add_label "bereich:ui"      "c2e0c6" "Oberfläche"
add_label "bereich:api"     "bfd4f2" "Schnittstellen und Import"
add_label "bereich:recht"   "e99695" "Datenschutz, Audit, Compliance"
add_label "prio:hoch"       "b60205" "Blockiert nachfolgende Arbeit"

# --------------------------------------------------------------- Meilensteine
echo "==> Meilensteine anlegen"
create_milestone() {
  gh api "repos/${REPO}/milestones" -f title="$1" -f description="$2" >/dev/null 2>&1 \
    && echo "    + $1" || echo "    = $1 (existiert)"
}

create_milestone "M1 Fundament"          "Datenmodell, Schema-Migration, Katalogverwaltung"
create_milestone "M2 Generator"          "Tätigkeitsprofile, Ticketketten, Soll-Ist-Prüfung"
create_milestone "M3 Veranstaltungen"    "Sammeltermine, Massenabschluss, Teilnehmerlisten"
create_milestone "M4 Matrix"             "Matrix-Ansicht, Ampel, Export"
create_milestone "M5 Automatisierung"    "Recurrence-/Reveille-Kopplung, Eskalation"
create_milestone "M6 Import"             "CSV-/REST-Import, HR-Sync, Ersterfassung"
create_milestone "M7 Audit & Datenschutz" "Berechtigungen, Löschkonzept, Auditbericht"
create_milestone "M8 Release 1.0"        "Doku, Testumgebung, Lasttest, Paketierung"

# --------------------------------------------------------------------- Issues
echo "==> Issues anlegen"
issue() { # $1=titel $2=body $3=milestone $4=labels
  gh issue create --repo "$REPO" --title "$1" --body "$2" --milestone "$3" --label "$4" >/dev/null
  echo "    + $1"
}

# -- M1 ---------------------------------------------------------------------
issue "F1.1 Schema-Migration" \
"Tabellen \`qt_massnahme\`, \`qt_person\`, \`qt_profil\`, \`qt_profil_massnahme\`, \`qt_zuordnung\`, \`qt_veranstaltung\` über die Mantis-Plugin-Schema-API anlegen. Installations- und Upgrade-Routine inkl. Rückbau bei Deinstallation.

Abhängig von Entscheidung E1 (führender Personenschlüssel)." \
"M1 Fundament" "typ:feature,bereich:daten,prio:hoch"

issue "F1.2 Maßnahmenkatalog CRUD" \
"Verwaltungsoberfläche für Maßnahmen: Schlüssel, Bezeichnung, Typ (UW/QU/QB/BE/VO), Intervall in Monaten, Rechtsgrundlage, Nachweisart, Vorlaufzeit für Eskalation." \
"M1 Fundament" "typ:feature,bereich:ui"

issue "F1.3 Vorbedingungen zwischen Maßnahmen" \
"Eine Maßnahme kann andere als Voraussetzung referenzieren (Qualifikation → Beauftragung → Unterweisung). Zyklenprüfung beim Speichern." \
"M1 Fundament" "typ:feature,bereich:daten"

issue "F1.4 Personenregister" \
"Personen unabhängig von Mantis-Benutzerkonten: Personalnummer, Name, Abteilung, Eintritt, Austritt, Vorgesetzter (Mantis-User-ID). Die meisten gewerblichen Mitarbeiter haben keinen Mantis-Account." \
"M1 Fundament" "typ:feature,bereich:daten,prio:hoch"

issue "F1.5 Custom-Field-Bootstrap" \
"Die im Konzept beschriebenen Custom Fields bei Installation automatisch anlegen und den Zielprojekten zuordnen. Bestehende gleichnamige Felder erkennen und wiederverwenden statt duplizieren." \
"M1 Fundament" "typ:feature,bereich:daten"

issue "F1.6 Konfigurationsseite" \
"Zielprojekt, Mapping der eigenen Statuswerte, Vorlaufzeiten je Eskalationsstufe, Empfängerkreise." \
"M1 Fundament" "typ:feature,bereich:ui"

issue "F1.7 Beispiel-Maßnahmenkatalog" \
"Importierbare YAML mit Jahresunterweisung, Brandschutz, Erste Hilfe, Hubarbeitsbühne (DGUV Grundsatz 308-008), Flurförderzeuge, Leitern und Tritte, Gefahrstoffe. Ausdrücklich als Startpunkt, nicht als Rechtskataster." \
"M1 Fundament" "typ:doku"

# -- M2 ---------------------------------------------------------------------
issue "F2.1 Tätigkeitsprofile" \
"Profil = benannte Menge von Maßnahmen, z. B. Hubarbeitsbühnenführer, Servicetechniker Außendienst, Bürotätigkeit." \
"M2 Generator" "typ:feature,bereich:daten"

issue "F2.2 Profilzuordnung Person ↔ Profil" \
"n:m-Zuordnung mit Gültigkeitszeitraum, damit Rollenwechsel historisch nachvollziehbar bleiben." \
"M2 Generator" "typ:feature,bereich:daten"

issue "F2.3 Ketten-Generator" \
"Erzeugt aus einer Profilzuordnung die Tickets in korrekter Reihenfolge inklusive \`depends on\`-Beziehungen. Kernstück des Plugins." \
"M2 Generator" "typ:feature,prio:hoch"

issue "F2.4 Native Terminplanung ohne Fremdabhaengigkeit" \
"Folgezyklen werden vom Plugin selbst erzeugt - vorausschauend oder ereignisgetrieben je nach Faelligkeitsmodus (F2.8).

Wichtig fuer die oeffentliche Veroeffentlichung: Es darf keine harte Abhaengigkeit zu einem anderen Plugin bestehen. Ist IssueRecurrence installiert, kann es optional erkannt und genutzt werden - erforderlich ist es nicht." \
"M2 Generator" "typ:feature,prio:hoch"

issue "F2.5 Soll-Ist-Prüfung" \
"Report: Personen mit Profil, aber ohne gültigen Nachweis. Zusätzlich der Sonderfall 'Beauftragung besteht, zugrunde liegende Qualifikation fehlt oder ist abgelaufen'." \
"M2 Generator" "typ:feature,bereich:ui,prio:hoch"

issue "F2.6 Dry-Run-Vorschau" \
"Vor der Erzeugung anzeigen, welche Tickets entstehen würden. Verhindert, dass ein Modellierungsfehler in tausenden Tickets landet." \
"M2 Generator" "typ:feature,bereich:ui"

issue "F2.8 Zwei Erzeugungsstrategien im Generator" \
"Der Fälligkeitsmodus ist kein Parameter einer Formel, sondern bestimmt, **wann** Tickets überhaupt entstehen können:

- **Vorausschauend** (\`kalenderjahr\`, \`stichmonat\`): Der komplette Jahrgang kann im Voraus gesammelt erzeugt werden. Voraussetzung dafür, dass die Terminplanung für Sammelunterweisungen in M3 funktioniert.
- **Ereignisgetrieben** (\`rollierend\`, \`extern\`): Das Folgeticket kann erst beim Abschluss des Vorgängers entstehen, weil vorher kein Fälligkeitsdatum existiert.

Beide Strategien sind zu implementieren, die Auswahl erfolgt anhand von F1.8." \
"M2 Generator" "typ:feature,prio:hoch"

issue "F2.7 Profiländerung behandeln" \
"Beim Wechsel des Tätigkeitsprofils entfallende Maßnahmen auf 'entfallen' setzen, neue erzeugen, bestehende gültige Nachweise erhalten." \
"M2 Generator" "typ:feature"

# -- M3 ---------------------------------------------------------------------
issue "F3.1 Veranstaltung anlegen" \
"Sammeltermin mit Maßnahme, Datum, Ort, Unterweisendem und Kapazität." \
"M3 Veranstaltungen" "typ:feature,bereich:ui"

issue "F3.2 Teilnehmerauswahl" \
"Auswahl aus fälligen Personen, filterbar nach Abteilung und Fälligkeitsdatum, mit Warnung bei Überschreiten der Kapazität." \
"M3 Veranstaltungen" "typ:feature,bereich:ui"

issue "F3.3 Kind-Tickets je Teilnehmer" \
"Je Teilnehmer ein Nachweisticket als Kind des Veranstaltungstickets erzeugen." \
"M3 Veranstaltungen" "typ:feature"

issue "F3.4 Massenabschluss" \
"Ein Klick setzt für alle Teilnehmer Status, Durchführungsdatum und Gültigkeitsende. Abwesende bleiben offen und werden zur Neuterminierung vorgemerkt. Löst das größte Handarbeitsproblem des Bordmittel-Ansatzes." \
"M3 Veranstaltungen" "typ:feature,prio:hoch"

issue "F3.5 Teilnehmerliste als PDF" \
"Druckbare Anwesenheitsliste mit Unterschriftenspalte, Maßnahmeninhalt und Rechtsgrundlage — das rechtlich maßgebliche Dokument." \
"M3 Veranstaltungen" "typ:feature,bereich:recht"

issue "F3.6 Nachweis-Anhang referenzieren" \
"Gescannte Liste einmal am Elternticket hinterlegen, in allen Kind-Tickets referenzieren statt kopieren." \
"M3 Veranstaltungen" "typ:feature,bereich:daten"

issue "F3.7 Terminvorschlag (1.1)" \
"Auf Basis fälliger Maßnahmen Veranstaltungstermine mit optimaler Teilnehmerzahl vorschlagen. Zurückgestellt auf Version 1.1." \
"M3 Veranstaltungen" "typ:feature"

# -- M4 ---------------------------------------------------------------------
issue "F4.1 Matrix-Ansicht" \
"Person × Maßnahme, Zellenfarbe nach Restgültigkeit, Zelle verlinkt auf das Ticket. Die Ansicht, die MantisBT nativ nicht kann." \
"M4 Matrix" "typ:feature,bereich:ui,prio:hoch"

issue "F4.2 Filter und Gruppierung" \
"Nach Abteilung, Profil, Maßnahmentyp und Status; Achsen tauschbar." \
"M4 Matrix" "typ:feature,bereich:ui"

issue "F4.3 Performance der Matrix" \
"Serverseitige Aggregation statt Ticket-für-Ticket-Auflösung. Zielwert: 500 Personen × 10 Maßnahmen unter 2 Sekunden." \
"M4 Matrix" "typ:infra,bereich:daten"

issue "F4.4 CSV-Export" \
"Matrix und Rohdaten exportierbar." \
"M4 Matrix" "typ:feature,bereich:api"

issue "F4.5 Audit-PDF" \
"Stichtagsbezogener Nachweisbericht mit Erfüllungsgrad je Abteilung — das Dokument für die ASA-Sitzung und das externe Audit." \
"M4 Matrix" "typ:feature,bereich:recht"

issue "F4.6 Kennzahlen-Widget" \
"Widget auf der Mantis-Startseite: eigener Erfüllungsgrad und überfällige Maßnahmen. Wichtigster Hebel für die Akzeptanz der Führungskräfte." \
"M4 Matrix" "typ:feature,bereich:ui"

# -- M5 ---------------------------------------------------------------------
issue "F5.1 Ablaufwächter" \
"Nächtlicher Lauf setzt abgelaufene Nachweise auf 'abgelaufen'. Idempotent." \
"M5 Automatisierung" "typ:feature,prio:hoch"

issue "F5.2 Ablaufreaktivierung, optional ueber Reveille" \
"Befristete Qualifikationen zurueckstellen und zum Ablaufdatum minus Vorlaufzeit reaktivieren.

Ist https://github.com/marcwoge/mantisBT-reveille installiert, wird dorthin delegiert; sonst greift ein eigener Fallback im Cron-Lauf. Pruefung zur Laufzeit ueber plugin_is_installed(), sauberes Degradieren ohne Fehler. Das Weckdatum liefert in beiden Faellen QT_DueDateCalculator." \
"M5 Automatisierung" "typ:feature,bereich:api"

issue "F5.3 Eskalationsstufen" \
"Vier konfigurierbare Stufen (90 / 30 / 0 / −30 Tage) mit unterschiedlichen Empfängerkreisen: Vorgesetzter, SiFa, Abteilungsleitung, Geschäftsführung." \
"M5 Automatisierung" "typ:feature"

issue "F5.4 Ruhensvermerk bei überfälliger Beauftragung" \
"Bei sicherheitsrelevanten Beauftragungen (Hubarbeitsbühne, Flurförderzeuge, Kran) nach Fristüberschreitung automatisch Vermerk setzen und die abhängige Beauftragung kennzeichnen. Der Punkt, an dem das System vom Erinnerungs- zum Steuerungswerkzeug wird." \
"M5 Automatisierung" "typ:feature,bereich:recht"

issue "F5.5 CLI-Runner qt_cron.php" \
"Analog zum IssueRecurrence-Runner, für Cron beziehungsweise systemd-Timer. Exit-Codes für Monitoring." \
"M5 Automatisierung" "typ:infra"

issue "F5.6 Laufprotokoll" \
"Jeder Automatiklauf wird protokolliert und ist in der Oberfläche einsehbar. Ein leeres Protokoll ist das früheste Warnsignal für einen ausgefallenen Cron." \
"M5 Automatisierung" "typ:feature,bereich:ui"

issue "F5.7 Moduswechsel im Bestand" \
"Wird der Fälligkeitsmodus einer Maßnahme im laufenden Betrieb geändert, verschieben sich potenziell tausende Termine. Vor der Übernahme muss eine Simulation die betroffenen Nachweise und die neuen Termine zeigen.

Bereits abgeschlossene Zyklen werden ausdrücklich **nicht** rückwirkend neu berechnet — das würde die Auditspur brechen. Der neue Modus greift ab dem nächsten Zyklus." \
"M5 Automatisierung" "typ:feature,bereich:daten"

# -- M6 ---------------------------------------------------------------------
issue "F6.1 CSV-Import Personen" \
"Personalstamm inklusive Abteilung und Vorgesetztem." \
"M6 Import" "typ:feature,bereich:api"

issue "F6.2 CSV-Import Bestandsnachweise" \
"Historische Nachweise mit Durchführungs- und Ablaufdatum, damit bei Inbetriebnahme nicht der gesamte Bestand fällig erscheint." \
"M6 Import" "typ:feature,bereich:api,prio:hoch"

issue "F6.3 REST-Endpunkte" \
"\`GET /qt/nachweise\`, \`GET /qt/personen\`, \`POST /qt/import\` mit inkrementellem Abruf über \`updated_since\` für die NiFi-Anbindung." \
"M6 Import" "typ:feature,bereich:api"

issue "F6.4 HR-Sync" \
"Wiederkehrender Abgleich: Neueintritt erzeugt Ketten mit Erstunterweisung binnen 14 Tagen, Austritt setzt offene Tickets auf 'entfallen', Abteilungswechsel hängt Zuständigkeiten um." \
"M6 Import" "typ:feature,bereich:api"

issue "F6.5 Dublettenprüfung beim Import" \
"Bestehende Nachweise über (Personalnummer, Maßnahme, Zeitraum) erkennen." \
"M6 Import" "typ:feature,bereich:daten"

issue "F6.6 Import-Rollback" \
"Jeder Import erhält eine Batch-ID und ist als Ganzes zurücknehmbar." \
"M6 Import" "typ:feature,bereich:daten"

# -- M7 ---------------------------------------------------------------------
issue "F7.1 Berechtigungsstufen" \
"Eigene Access Level: Betrachter (nur eigene Abteilung), Sachbearbeiter, SiFa, Administrator." \
"M7 Audit & Datenschutz" "typ:feature,bereich:recht,prio:hoch"

issue "F7.2 Trennung arbeitsmedizinische Vorsorge" \
"Maßnahmen vom Typ VO ausschließlich im gesonderten Projekt sichtbar, Feldsatz technisch auf Art, Datum und Nachuntersuchungsfrist beschränkt. Befunde dürfen technisch nicht speicherbar sein." \
"M7 Audit & Datenschutz" "typ:feature,bereich:recht,prio:hoch"

issue "F7.3 Löschkonzept" \
"Aufbewahrungsfrist je Maßnahmenart, Löschvorschlagsliste, protokollierte Löschung. Bei Gefahrstoffexposition gelten teils jahrzehntelange Fristen." \
"M7 Audit & Datenschutz" "typ:feature,bereich:recht"

issue "F7.4 Auskunftsexport nach DSGVO Art. 15" \
"Alle zu einer Person gespeicherten Daten als PDF." \
"M7 Audit & Datenschutz" "typ:feature,bereich:recht"

issue "F7.5 Änderungsprotokoll Plugin-Stammdaten" \
"Änderungen an Katalog und Profilen analog zur Mantis-History protokollieren." \
"M7 Audit & Datenschutz" "typ:feature,bereich:daten"

issue "F7.6 Vorlage Verarbeitungsverzeichnis" \
"Vorlagentext als Anhang der Dokumentation." \
"M7 Audit & Datenschutz" "typ:doku,bereich:recht"

# -- M8 ---------------------------------------------------------------------
issue "F8.1 Docker-Testumgebung" \
"Compose-Setup mit MantisBT, MariaDB und Seed-Daten (50 Personen, 8 Maßnahmen)." \
"M8 Release 1.0" "typ:infra"

issue "F8.2 Lasttest" \
"Nachweis der Zielgrößen: 500 Personen, 25.000 Tickets, Matrix unter 2 Sekunden." \
"M8 Release 1.0" "typ:infra"

issue "F8.3 Übersetzungen DE/EN" \
"Vollständige Sprachdateien." \
"M8 Release 1.0" "typ:doku"

issue "F8.4 Administratorhandbuch" \
"Installation, Konfiguration, Betrieb, Backup, Upgrade." \
"M8 Release 1.0" "typ:doku"

issue "F8.5 Anwenderhandbuch" \
"Kurzanleitungen für Führungskräfte und Sachbearbeitung, je höchstens zwei Seiten. Längere Anleitungen werden nicht gelesen." \
"M8 Release 1.0" "typ:doku"

issue "F8.6 Migrationspfad aus der Bordmittel-Konfiguration" \
"Bestehende Tickets aus der reinen Mantis-Konfiguration in die Plugin-Datenstruktur überführen, ohne die Historie zu verlieren." \
"M8 Release 1.0" "typ:feature,bereich:daten"

issue "F8.7 Paketierung und Changelog" \
"Release-Artefakt, Versionierung, Changelog." \
"M8 Release 1.0" "typ:infra"

# -- Offene fachliche Entscheidungen ----------------------------------------
issue "E1 Führender Personenschlüssel festlegen" \
"Ist die Personalnummer führend, oder braucht es eine plugin-eigene ID für Personen ohne Personalnummer (Leiharbeit, Fremdfirmen)? Blockiert F1.1 und F1.4." \
"M1 Fundament" "typ:entscheidung,prio:hoch"

issue "E2 Fremdfirmenmitarbeiter mitführen?" \
"Wenn ja: eigener Personentyp mit reduziertem Feldsatz und eigener Löschfrist." \
"M1 Fundament" "typ:entscheidung"

issue "E3 Anlassbezogene Unterweisungen" \
"Unterweisungen nach Unfall oder bei neuer Anlage haben kein Intervall. Eigener Maßnahmentyp UA oder Sonderfall des bestehenden Modells?" \
"M1 Fundament" "typ:entscheidung"

issue "F1.8 Fälligkeitsmodus je Maßnahme" \
"Vier Modi statt einer globalen Festlegung:

- \`rollierend\` — Durchführung + Intervall
- \`kalenderjahr\` — 31.12. des Zieljahres
- \`stichmonat\` — Ende eines definierten Monats im Zieljahr, je Abteilung staffelbar
- \`extern\` — keine Berechnung, Gültigkeitsende wird aus dem Nachweis übernommen

\`extern\` ist kein Randfall: Bei Ersthelfer-Bescheinigungen und arbeitsmedizinischer Vorsorge setzt der Träger beziehungsweise der Betriebsarzt das Datum. Rechnet das System selbst, weicht der Systemstand vom Dokument ab — im Audit gilt das Dokument.

Global konfigurierbarer Vorgabewert, je Maßnahme überschreibbar. Ersetzt die frühere offene Entscheidung E4." \
"M1 Fundament" "typ:feature,bereich:daten,prio:hoch"

issue "F1.9 Karenzzeit und Ankererhalt" \
"Bei rollierendem Modus entsteht Vorwärtsdrift: Wird sechs Wochen vor Fälligkeit unterwiesen, rutscht der Folgezyklus um sechs Wochen nach vorn. Über Jahre wird aus dem Jahres- ein Zehnmonatsintervall.

Gegenmaßnahme wie bei der Hauptuntersuchung: Feld \`soll_termin\` je Nachweis. Bei Durchführung innerhalb der Karenzzeit vor dem Soll-Termin wird der Folgezyklus vom Soll- statt vom Ist-Datum berechnet.

Das Feld muss von Anfang an vorhanden sein — der ursprüngliche Anker lässt sich aus dem Durchführungsdatum nicht rekonstruieren." \
"M1 Fundament" "typ:feature,bereich:daten,prio:hoch"

issue "E5 Halbjahresintervall für Jugendliche" \
"Automatische Ableitung aus dem Geburtsdatum würde die Speicherung eines weiteren personenbezogenen Datums erfordern. Alternative: manuelles Kennzeichen am Personendatensatz." \
"M1 Fundament" "typ:entscheidung,bereich:recht"

issue "E6 Maßnahmenkatalog je Standort" \
"Ist ein gemeinsamer Katalog ausreichend oder muss er mandantenfähig sein?" \
"M1 Fundament" "typ:entscheidung"

echo
echo "==> Fertig. Repository: https://github.com/${REPO}"
