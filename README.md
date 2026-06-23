# Monatsblitz Plugin

Ein kleines WordPress-Plugin zur Verwaltung von Blitzturnieren über eine REST-API.

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

Aktuell sind die REST-Endpunkte in der Plugin-Implementierung ungeschützt (`permission_callback => __return_true`).
Vor dem produktiven Einsatz sollte eine Authentifizierung ergänzt werden, z. B. WordPress Application Passwords oder JWT.

## Hinweis

Die Domain `https://kindermaenner.de` wird vom Plugin nicht hardcodiert. Es arbeitet unabhängig von der Site-Adresse und nutzt WordPress-interne Pfade/URLs.
