# Lokale MantisBT-Testumgebung

Komplett lauffähige MantisBT-Instanz zum Ausprobieren des **QualificationTracker**-
Plugins – inklusive Datenbank und einem Mail-Catcher, der alle ausgehenden
E-Mails abfängt (es wird also nichts wirklich verschickt).

Die Ports weichen bewusst von der IssueRecurrence-Testumgebung ab, damit beide
gleichzeitig laufen können.

| Dienst | URL | Zugang |
| --- | --- | --- |
| MantisBT | http://localhost:8990 | `administrator` / `root` |
| Mailpit (E-Mails ansehen) | http://localhost:8026 | – |
| MariaDB | intern `db:3306` | `mantisbt` / `mantisbt` |

> Reines Test-Setup. Die Zugangsdaten/Salt in `docker-compose.yml` und
> `config_inc.php` sind absichtlich simpel – **nicht in Produktion verwenden.**

## Starten

Aus dem Projekt-Stammverzeichnis (eine Ebene über `docker/`):

```bash
docker compose up -d --build
```

Beim ersten Start wird das MantisBT-Image gebaut (lädt das offizielle Release
herunter). Danach **einmalig** den Installer abschließen:

1. http://localhost:8990/admin/install.php öffnen.
2. Die Datenbankfelder sind bereits aus `docker/config_inc.php` vorbefüllt
   (Host `db`, DB `bugtracker`, Benutzer `mantisbt`/`mantisbt`).
3. Auf **„Install/Upgrade Database"** klicken → die Tabellen werden angelegt.
4. Fertig. Anmelden mit `administrator` / `root`.

## Plugin installieren

1. In MantisBT: **Manage → Manage Plugins**.
2. Bei *QualificationTracker* auf **Install** klicken.
3. Konfigurieren unter **Manage → Manage Plugins → QualificationTracker**.

Das Plugin ist bereits in den Container gemountet
(Repo-Stamm → `/var/www/html/plugins/QualificationTracker`). Änderungen an den
Plugin-Dateien wirken sofort, ohne Neubau.

## Demo-Daten laden

Nach der Plugin-Installation füllt ein Seed-Skript die Testumgebung mit
reproduzierbaren, **rein synthetischen** Daten (Personalnummern ab 900000, keine
echten Personen): der mitgelieferte Beispielkatalog (11 Maßnahmen), 50 Personen,
drei Tätigkeitsprofile und je eine Zuordnung.

```bash
docker compose exec mantis php plugins/QualificationTracker/scripts/qt_seed.php --reset
```

Das Skript ist wiederholbar (Personen werden über die Personalnummer, Profile
über den Namen abgeglichen). `--reset` löscht vorher die Plugin-Stammdaten für
einen sauberen Stand; ohne `--reset` wird nur ergänzt/aktualisiert.

Nachweistickets erzeugt der Seed bewusst nicht — dafür ein **Zielprojekt**
konfigurieren (Manage → QualificationTracker → Konfiguration) und anschließend
den Generator laufen lassen (Manage → QualificationTracker → Trockenlauf bzw.
Generieren).

## Lasttest

Ein Skript weist die Zielgrößen nach (500 Personen, ≥ 25.000 Nachweise, Matrix
unter 2 s). Es legt synthetische Personen (Personalnummern ab 800000), ein
Profil über den gesamten Katalog und die nötigen Nachweis-Index-Zeilen an und
misst die drei schwersten Lesepfade.

```bash
docker compose exec mantis php plugins/QualificationTracker/scripts/qt_loadtest.php
# danach die Testdaten wieder entfernen:
docker compose exec mantis php plugins/QualificationTracker/scripts/qt_loadtest.php --cleanup
```

Die Matrix liest per Konstruktion (Entscheidung G5) den denormalisierten
Nachweis-Index `qt_nachweis`, nicht die MantisBT-Ticket-Tabelle — eine
Index-Zeile je Nachweis-Instanz entspricht einem Ticket. Der Lasttest füllt
daher 25.000 Index-Zeilen (das, was die Matrix aggregiert) statt 25.000
Core-Bug-Zeilen anzulegen.

Referenzmessung (Docker, PHP 8.2, MariaDB 10.11): 550 Personen × 11 Maßnahmen,
27.500 Nachweise → vollständige Matrix ~220 ms, Matrix-Seite ~200 ms,
Auditbericht ~160 ms, Spitzenspeicher 66 MB. Alle Pfade deutlich unter der
2-Sekunden-Grenze.

## Häufige Befehle

```bash
# Logs ansehen
docker compose logs -f mantis

# In den MantisBT-Container
docker compose exec mantis bash

# Stoppen (Daten bleiben erhalten)
docker compose down

# Stoppen und ALLES löschen (DB-Daten zurücksetzen)
docker compose down -v
```

## Hinweis zu Windows / Git Bash

Wird `docker compose exec` aus Git Bash mit einem absoluten Container-Pfad
aufgerufen, wandelt Git Bash den Pfad evtl. um. Dann die Variante mit
`MSYS_NO_PATHCONV=1` davor verwenden.
