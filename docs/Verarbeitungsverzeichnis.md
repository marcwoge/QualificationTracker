<!--
  QualificationTracker – Vorlage Verarbeitungsverzeichnis (F7.6)
  Vorlagentext für das Verzeichnis von Verarbeitungstätigkeiten nach
  Art. 30 Abs. 1 DSGVO, bezogen auf den Einsatz dieses Plugins.

  Dies ist eine Vorlage, kein Rechtsrat. Passen Sie die mit «…» markierten
  Stellen an Ihre Organisation an und lassen Sie das Ergebnis von Ihrem/Ihrer
  Datenschutzbeauftragten prüfen. Die im Plugin hinterlegte Konfiguration
  (Aufbewahrungsfristen, Projekte, Rollen) zeigt die Seite „Verarbeitungs-
  verzeichnis" mit den tatsächlichen Werten und als Druck-/PDF-Ansicht an.

  @package   QualificationTracker
  @author    Marc-Philipp Woge <marc.woge@googlemail.com>
  @copyright Copyright (c) 2026 Marc-Philipp Woge
  @license   MIT
-->

# Verzeichnis von Verarbeitungstätigkeiten (Art. 30 Abs. 1 DSGVO)

**Verarbeitungstätigkeit:** Verwaltung gesetzlich erforderlicher Unterweisungen,
Qualifikationen und Beauftragungen (Arbeitsschutz) mit dem MantisBT-Plugin
*QualificationTracker*.

> **Hinweis:** Diese Vorlage beschreibt ausschließlich die Verarbeitung durch
> dieses Plugin. Sie ersetzt nicht das Gesamtverzeichnis Ihrer Organisation und
> keine rechtliche Beratung. Ersetzen Sie die mit «…» markierten Angaben.

---

## 1. Verantwortlicher

| Angabe | Wert |
|---|---|
| Verantwortlicher (Art. 4 Nr. 7 DSGVO) | «Name und Anschrift der verantwortlichen Stelle» |
| Gesetzlicher Vertreter | «Name» |
| Datenschutzbeauftragte(r) | «Name, E-Mail, Telefon» |
| Fachlich zuständige Stelle | «z. B. Fachkraft für Arbeitssicherheit / HSE» |

## 2. Zwecke der Verarbeitung

- Nachweis und Steuerung gesetzlich vorgeschriebener Unterweisungen nach
  **ArbSchG § 12** und **DGUV Vorschrift 1 § 4**.
- Verwaltung befristeter Qualifikationen und Beauftragungen sowie ihrer
  fachlichen Abhängigkeiten (Vorbedingungen).
- Fristenüberwachung, Erinnerung und Eskalation vor Ablauf von Nachweisen.
- Erbringung des Nachweises der Erfüllung im Audit- und Haftungsfall.

## 3. Kategorien betroffener Personen

- Beschäftigte der verantwortlichen Stelle.
- Fremd- und Leihpersonal mit Unterweisungspflicht im Einsatzbetrieb.

## 4. Kategorien personenbezogener Daten

| Kategorie | Beispiele |
|---|---|
| Identifikationsdaten | Name, Personalnummer, Abteilung, Personentyp |
| Beschäftigungsdaten | Ein-/Austritt, Vorgesetzte(r), ggf. Fremdfirma |
| Qualifikationsdaten | zugewiesene Tätigkeitsprofile, Nachweis-Instanzen (Maßnahme, Soll-Termin, Gültigkeitsende, Status), Veranstaltungsteilnahmen |
| Jugendschutz | Enddatum des verkürzten Unterweisungsintervalls (JArbSchG § 29) – **kein Geburtsdatum** |
| Besondere Kategorien (Art. 9) | arbeitsmedizinische Vorsorge (VO) **nur als Metadaten**: Art, Termine, Status – **kein Befund, keine Diagnose** (im Datenmodell nicht vorgesehen) |

## 5. Kategorien von Empfängern

- Fachlich zuständige Stelle (Arbeitssicherheit) und Vorgesetzte im Rahmen ihrer
  Zuständigkeit (rollenbasierter Zugriff, siehe Nr. 9).
- Arbeitsmedizinischer Dienst – ausschließlich für Vorsorge-Daten, über ein
  getrenntes, zugriffsbeschränktes Projekt.
- «weitere interne Empfänger, z. B. Personalabteilung»
- Auftragsverarbeiter: «nur falls zutreffend, z. B. externer Hosting-Dienstleister mit AV-Vertrag»

## 6. Rechtsgrundlagen

- **Art. 6 Abs. 1 lit. c DSGVO** i. V. m. ArbSchG § 12, DGUV Vorschrift 1 § 4
  (Erfüllung einer rechtlichen Verpflichtung – Unterweisungspflicht).
- **§ 26 Abs. 1 BDSG** (Datenverarbeitung für Zwecke des Beschäftigungsverhältnisses).
- Für arbeitsmedizinische Vorsorge: **Art. 9 Abs. 2 lit. b DSGVO** i. V. m.
  ArbMedVV; die Trennung stellt sicher, dass nur befugte Stellen Zugriff haben.

## 7. Löschfristen

Nachweise werden nach Ablauf einer je Maßnahmentyp konfigurierten
**Aufbewahrungsfrist** zur Löschung vorgeschlagen; die Löschung erfolgt
kontrolliert durch eine administrative Stelle und wird protokolliert (Plugin-
Funktion „Löschkonzept").

| Maßnahmentyp | Aufbewahrungsfrist |
|---|---|
| Standard (Vorgabe) | «Vorgabewert, Standard 36 Monate» |
| Unterweisung (UW) | «Monate» |
| Qualifikation, unbefristet (QU) | «Monate» |
| Qualifikation, befristet (QB) | «Monate» |
| Beauftragung (BE) | «Monate» |
| Vorsorge (VO) | «Monate» |

> Die tatsächlich konfigurierten Werte zeigt die Plugin-Seite
> „Verarbeitungsverzeichnis" bzw. die Konfigurationsseite an.

## 8. Übermittlung in Drittländer

Keine. Sämtliche Daten verbleiben in der von der verantwortlichen Stelle
betriebenen MantisBT-Installation. Das Plugin überträgt keine Daten an Dritte
und bindet keine externen Dienste ein.

## 9. Technische und organisatorische Maßnahmen (TOM)

- **Rollenbasierter Zugriff:** vier abgestufte Stufen (Betrachter,
  Sachbearbeiter, Fachkraft/SiFa, Administrator); Betrachter optional auf die
  eigene Abteilung beschränkt.
- **Trennung besonderer Kategorien:** Vorsorge-Nachweise (VO) in einem
  gesonderten Projekt; technische Feldbeschränkung; kein Freitext-Befundfeld.
- **Löschkonzept:** typbezogene Aufbewahrungsfristen, protokollierte Löschung.
- **Nachvollziehbarkeit:** unveränderbare MantisBT-Ticket-Historie je Nachweis
  sowie ein plugin-eigenes Änderungsprotokoll für Katalog, Profile und
  Zuordnungen.
- **Betroffenenrechte:** Auskunft nach Art. 15 als exportierbares Dokument.
- «ergänzende TOM Ihrer Organisation: Zugangskontrolle, Verschlüsselung im
  Transport (TLS), Backup-Konzept, Berechtigungsverwaltung»

---

*Stand: «Datum» · Version: «lfd. Nr.» · erstellt durch: «Name»*
