# Monatsblitz Plugin

Ein kleines WordPress-Plugin zur Verwaltung von Blitzturnieren über eine REST-API.

## Motivation

Wir sind beide Vorstandsmitglieder in unterschiedlichen Vereinen. Beruf, Familie und Ehrenamt unter einen Hut zu bringen bedeutet: Zeit ist unser knappstes Gut.
Gleichzeitig ist eine gepflegte Webseite heute ein entscheidender Faktor für Mitgliedergewinnung und Außenwirkung. Besonders im Schachverein zeigt sich Aktivität vor allem durch regelmäßige Ergebnisse und Turnierberichte.

Genau hier entsteht das Problem:
- Ergebnisse liegen oft nur auf Zetteln vor
- Der Zettel ist irgendwo, jemand hat ihn mitgenommen
- Im Chat wird er zwar geteilt, aber nie wiedergefunden
- Als Foto in WordPress wirkt es unprofessionell
- Und das Schreiben der News kostet jedes Mal unnötig Zeit

In unserem Schachverein gibt es im Schnitt zwei Rundenturniere pro Monat. Die Ergebnisse interessieren die Mitglieder, und sie zeigen nach außen: Hier passiert etwas. Aber die manuelle Pflege ist mühsam und fehleranfällig.

Daraus entstand die Idee:

Warum nicht die Ergebnisse direkt digital erfassen und WordPress die Beiträge automatisch erzeugen lassen?

Die Lösung besteht aus zwei Teilen:
1. WordPress‑Plugin
Es erzeugt automatisch News‑Beiträge, sobald neue Ergebnisse eintreffen.
Keine Copy‑Paste‑Arbeit, keine Zettel, keine verlorenen Informationen.

2. Mobile App
Alle Mitglieder können die Ergebnisse direkt am Handy eintragen.
Der Zettel entfällt komplett, und die Daten landen sofort dort, wo sie hingehören.

Damit lösen wir gleich mehrere Probleme gleichzeitig:
- Keine Papierzettel mehr
- Keine Suche nach Fotos oder Chat‑Nachrichten
- Ergebnisse sind sofort online
- Die Webseite bleibt aktuell und professionell
- Wir sparen Zeit, ohne auf Inhalte zu verzichten
- Mitglieder sehen sofort, was im Verein passiert

Kurz:
Wir automatisieren die langweiligen Teile, damit mehr Zeit für das bleibt, was im Verein wirklich zählt.

## Was das Plugin macht

- Legt beim Aktivieren die benötigten Tabellen an:
  - `wp_monatsblitz_players`
  - `wp_monatsblitz_tournaments`
  - `wp_monatsblitz_games`
  - `wp_monatsblitz_results`
- Stellt REST-API-Endpunkte bereit zur Verwaltung von Spielern, Turnieren, Spielen und Ergebnissen.
- Erzeugt aus einem vorhandenen Beitrag `Template_Monatsblitz` einen neuen veröffentlichten Beitrag, sobald ein Turnier finalisiert wird.
- Füllt dabei Platzhalter im Template wie `{{month_name}}`, `{{year}}`, `{{date}}`, `{{winner_name}}`, `{{winner_games}}`, `{{winner_points}}`, `{{table}}` und mehr.

## Einsatz

1. Plugin in `wp-content/plugins/monatsblitz` ablegen.
2. Im WordPress-Admin aktivieren.
3. Einen Beitrag mit dem Titel `Template_Monatsblitz` anlegen und als Vorlage nutzen.
   - Das Template kann HTML, Kadence-Blöcke und CSS enthalten.
   - Die Platzhalter werden beim Finalize-Call ersetzt.
4. App oder externes System nutzt die API, um den Blitzabend zu speichern.
5. Nach Abschluss der Ergebnisse wird `POST /wp-json/monatsblitz/v1/finalize` aufgerufen.
6. Das Plugin erzeugt einen neuen veröffentlichten Beitrag mit dem formatierten Ergebnis.

## REST-API

Basis: `/wp-json/monatsblitz/v1`

### Spieler

- `POST /player`
  - Legt einen neuen Spieler an oder gibt bei vorhandenem Spieler die ID zurück.
  - Body (JSON):
    - `forename`: Vorname
    - `surname`: Nachname

- `GET /players`
  - Gibt alle Spieler zurück.

### Turniere

- `POST /tournament`
  - Legt ein neues Turnier an.
  - Body (JSON):
    - `date`: Datum im Format `YYYY-MM-DD`

- `GET /tournaments`
  - Gibt alle Turniere zurück.

- `GET /tournament/{id}`
  - Gibt die Turnierdetails zu einer ID zurück.

### Spiele

- `POST /game`
  - Legt ein Spiel an.
  - Body (JSON):
    - `tournament_id`
    - `player1_id`
    - `player2_id`
    - `result` (`1-0`, `0-1`, `0.5-0.5`)

- `GET /games/{tournament_id}`
  - Gibt alle Spiele eines Turniers zurück.

### Ergebnisse

- `POST /result`
  - Legt ein Ergebnis für einen Spieler im Turnier an oder aktualisiert es.
  - Body (JSON):
    - `tournament_id`
    - `player_id`
    - `points`
    - `rank`

- `GET /results/{tournament_id}`
  - Gibt alle Ergebnisse eines Turniers zurück.

### Finalisierung

- `POST /finalize`
  - Erzeugt aus dem Beitrag `Template_Monatsblitz` einen neuen veröffentlichten Beitrag.
  - Body (JSON):
    - `tournament_id`

- Wichtig:
  - Der Beitrag wird direkt veröffentlicht.
  - Der Titel wird im Format `0Monatsblitz YYYY-MM-DD` angelegt.
  - Die Platzhalter im Template werden ersetzt.

## Datenbankschema

Das Projekt verwendet ein relationales Datenmodell, das speziell für die Anforderungen des Monatsblitz‑Turniersystems entwickelt wurde.

Die Datenbank besteht aus vier Tabellen:
- monatsblitz_players: Verwaltung der Spieler
- monatsblitz_tournaments: Metadaten zu jedem Turnier
- monatsblitz_games: alle gespielten Partien
- monatsblitz_results: Endergebnisse pro Spieler und Turnier

Das vollständige Schema ist im folgenden Diagramm dargestellt:

![Datenbankschema](./docs/db_scheme.png)

(Hinweis: Das Diagramm wurde mit drawSQL erstellt.)

### Designprinzipien

#### Eindeutigkeit durch Constraints

- monatsblitz_players: UNIQUE(forename, surname)
- monatsblitz_tournaments: UNIQUE(year, month, day)
- monatsblitz_games: UNIQUE(tournament_id, player1_id, player2_id, leg_type)
- monatsblitz_results: UNIQUE(tournament_id, player_id)

#### Flexible Rundenzahl  
Das Feld round_count in der Turniertabelle erlaubt beliebig viele Durchgänge (1 = einfache Runde, 2 = Hin/Rückrunde, …).

#### Explizite Rundenkennzeichnung  
Jede Partie besitzt ein Feld leg_type (1, 2, 3 …), das angibt, zu welchem Durchgang sie gehört.

#### WordPress‑kompatibel  
Das Schema ist vollständig kompatibel mit dbDelta() und verzichtet bewusst auf Foreign Keys.

## Platzhalter im Template

Im Beitrag `Template_Monatsblitz` können folgende Platzhalter genutzt werden:

- `{{month_name}}` — ausgeschriebener Monat
- `{{year}}` — Jahr
- `{{date}}` — Datum im Format `DD.MM.YYYY`
- `{{winner_name}}` — Name des Siegers
- `{{winner_games}}` — Anzahl seiner Spiele im Turnier
- `{{winner_points}}` — Punkte des Siegers
- `{{ranking_rows}}` — HTML-Zeilen für die Rangliste
- `{{games_list}}` — einfache Liste der Spiele
- `{{table}}` — komplette Kreuztabelle im HTML-Format

## Sicherheit

Die API ist durch einen API-Key geschützt, der im Header übertragen werden muss. Der Key kann in den Plugin-Einstellungen generiert werden und muss in der zugehörigen App in der Konfiguration angegeben werden.

## Hinweis

Die Domain `https://kindermaenner.de` wird vom Plugin nicht hardcodiert. Es arbeitet unabhängig von der Site-Adresse und nutzt WordPress-interne Pfade/URLs.
