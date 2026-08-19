<!--
  QualificationTracker – Anwenderhandbuch (F8.5)
  Zwei Kurzanleitungen: für Führungskräfte und für die Sachbearbeitung,
  je auf höchstens zwei Seiten.

  @package   QualificationTracker
  @author    Marc-Philipp Woge <marc.woge@googlemail.com>
  @copyright Copyright (c) 2026 Marc-Philipp Woge
  @license   MIT
-->

# Anwenderhandbuch

Zwei kurze Anleitungen für die tägliche Arbeit. Installation, Konfiguration und
Betrieb stehen im [Administratorhandbuch](Administratorhandbuch.md).

- [Teil A – Für Führungskräfte](#teil-a--für-führungskräfte) (überwachen)
- [Teil B – Für die Sachbearbeitung](#teil-b--für-die-sachbearbeitung) (pflegen)

---

## Teil A – Für Führungskräfte

**Ihre Aufgabe:** den Stand der Unterweisungen und Qualifikationen Ihres Bereichs
im Blick behalten und bei Fälligkeiten handeln. Sie müssen nichts eintragen –
Sie lesen und reagieren.

### Wo Sie den Überblick finden

- **Startseiten-Kachel.** Auf der MantisBT-Startseite sehen Sie oben eine Kachel
  „Qualifikationen – Überblick": den Erfüllungsgrad in Prozent und die Zahl der
  abgelaufenen, fehlenden und in Bearbeitung befindlichen Nachweise. Ein Klick
  führt in die Matrix oder den Auditbericht.
- **Seitenleiste → „Qualifikationen".** Öffnet direkt die **Matrix**.

### Die Matrix lesen

Die Matrix zeigt Personen (Zeilen) gegen Maßnahmen (Spalten). Jede Zelle ist eine
Ampel:

| Farbe / Text | Bedeutung | Handlung |
|---|---|---|
| **gültig** | Nachweis vorhanden und gültig | nichts zu tun |
| **läuft bald ab** | Gültigkeit endet demnächst | Erneuerung anstoßen lassen |
| **in Bearbeitung** | Nachweis geplant/offen | im Zeitplan verfolgen |
| **abgelaufen** | Gültigkeit ist abgelaufen | **vorrangig** klären |
| **kein Nachweis** | erforderlich, aber nichts vorhanden | Sachbearbeitung informieren |
| **nicht zugeordnet** | Maßnahme für diese Person nicht erforderlich | – |

Sind Sie als Betrachter einer Abteilung zugeordnet, zeigt die Matrix nur Ihren
Bereich.

### Filtern und auswerten

- In der Matrix nach **Abteilung**, **Status** oder **Maßnahmentyp** filtern, um
  z. B. alle „abgelaufen"-Fälle zu sehen.
- Der **Auditbericht** zeigt den Erfüllungsgrad je Abteilung zu einem Stichtag –
  geeignet als Nachweis im Audit- oder Begehungsgespräch. Er lässt sich als CSV
  exportieren.

### Wenn Sie benachrichtigt werden

Nähert sich ein Nachweis seinem Ablauf, erhalten Sie als zuständige Führungskraft
gestufte Erinnerungen am jeweiligen Ticket (typisch 90/30/0 Tage vorher und nach
Ablauf). Öffnen Sie das Ticket, stimmen Sie den Termin mit der Sachbearbeitung ab
und bestätigen Sie nach Durchführung – den Rest übernimmt das System.

### Was Sie **nicht** tun müssen

Fälligkeiten berechnen, Folgetickets anlegen, abgelaufene Nachweise markieren –
das erledigt die nächtliche Automatik. Ihre Aufmerksamkeit gilt den roten Zellen.

---

## Teil B – Für die Sachbearbeitung

**Ihre Aufgabe:** Stammdaten pflegen, Nachweise erfassen und Sammeltermine
organisieren. Alle genannten Punkte finden Sie unter **Verwaltung →
QualificationTracker**.

### Personen pflegen

- **Einzeln:** *Personen* → Neu. Pflichtfeld ist der Nachname; Personalnummer,
  Abteilung, Vorgesetzte(r) und Eintrittsdatum sollten gepflegt sein, weil sie
  Fälligkeiten und Zuständigkeiten steuern.
- **Als Liste:** *Import Personen* – eine semikolongetrennte CSV mit Kopfzeile.
  Der **Trockenlauf** zeigt vorab, was angelegt/aktualisiert würde und welche
  Zeilen Fehler haben. Erst danach ohne Trockenlauf ausführen.
- Für Leih-/Fremdpersonal den Personentyp entsprechend setzen und die Fremdfirma
  angeben.

### Einen einzelnen Nachweis abschließen

Ist eine Unterweisung durchgeführt worden, öffnen Sie das zugehörige Ticket und
nutzen **Abschließen**: Durchführungsdatum eintragen, ggf. Nachweisdokument als
**Anhang** hochladen, speichern. Das System berechnet den nächsten Fälligkeits-
zyklus und legt bei wiederkehrenden Maßnahmen das Folgeticket an.

Ist eine Maßnahme für eine Person nicht mehr erforderlich (z. B. Tätigkeits-
wechsel), setzen Sie den Nachweis auf **entfallen**, statt ihn zu löschen.

### Einen Sammeltermin organisieren

Für Gruppenunterweisungen sparen die **Veranstaltungen** viel Arbeit:

1. *Veranstaltungen* → Neu: Maßnahme, Termin, Ort, Unterweisende(r), optional eine
   Kapazität.
2. **Teilnehmerliste** füllen – Personen auswählen, für die die Maßnahme
   erforderlich ist. Für jede entsteht ein Nachweisticket, mit der Veranstaltung
   verknüpft.
3. Nach dem Termin den **Massenabschluss** ausführen: alle Anwesenden in einem
   Schritt als „teilgenommen" abschließen; Abwesende bleiben offen.

### Bestandsnachweise importieren

Beim Einstieg oder nach einer Migration: *Import Nachweise* – CSV mit
Durchführungs- und Ablaufdaten. So werden historische Nachweise in den richtigen
Zustand versetzt, ohne dass am Tag eins alles gleichzeitig fällig wird. Auch hier
zuerst der **Trockenlauf**.

### Tägliche Routine

- Neue **rote** Matrix-Zellen (abgelaufen/kein Nachweis) abarbeiten.
- Anstehende Termine als Veranstaltung bündeln.
- Ein- und Austritte im Personalstamm nachziehen.

### Was Sie **nicht** anfassen müssen

Maßnahmenkatalog, Tätigkeitsprofile und die Konfiguration pflegt die Fachkraft
für Arbeitssicherheit bzw. die Administration. Löschungen nach Ablauf der
Aufbewahrungsfrist laufen über das administrative **Löschkonzept**.

---

*Stand: Release 1.0. Änderungen siehe [CHANGELOG.md](../CHANGELOG.md).*
