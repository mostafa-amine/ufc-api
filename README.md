# Unofficial UFC API

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
![PHP 8.3](https://img.shields.io/badge/PHP-8.3-777BB4)
![Laravel 13](https://img.shields.io/badge/Laravel-13-FF2D20)

A hosted, open-source REST API exposing the **full depth** of [ufcstats.com](http://ufcstats.com)
data — every event, fight, fighter, **round-by-round statistic**, significant-strike
**target/position breakdown**, and **judge scorecard** — through a clean, documented, queryable
interface.

## Why this exists

The existing landscape splits two ways: detailed **datasets** (rich CSV dumps, no API) and
**APIs** (shallow — fighters and rankings only, no round-by-round depth). ufcstats is the hard
ceiling on detail; nobody can have *more* data than the source. The gap is turning that depth
into a well-built, queryable API. That's this project.

What it does that others don't:

- **Round-by-round stats**, queryable and relational — not just fight totals.
- **Significant-strike breakdown** by target (head / body / leg) and position
  (distance / clinch / ground), per round.
- **Judge scorecards**, mapped to the correct fighter. *(ufcstats lists scores as
  `loser - winner`, not by corner; naive scrapers assign them positionally and corrupt ~half
  their scorecards. We map by the bout winner — verified across unanimous and split decisions.)*
- Full fight metadata: referee, finish detail, time format, bout order, title-bout flag.
- Stable IDs that match ufcstats, so you can always cross-reference the source.

## API

Base path: `/v1`. Responses are JSON. List endpoints are paginated (`data` + `meta` + `links`).

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/v1/register` | Get a free API key |
| `GET` | `/v1/health` | Service health + row counts (public) |
| `GET` | `/v1/events` | List events — `?status= &from= &to= &search= &sort=-date &per_page=` |
| `GET` | `/v1/events/{id}` | Event + its bouts |
| `GET` | `/v1/fighters` | List/search fighters — `?search= &stance= &sort=name` |
| `GET` | `/v1/fighters/{id}` | Fighter profile + career averages |
| `GET` | `/v1/fighters/{id}/fights` | A fighter's bout history |
| `GET` | `/v1/fights` | List fights — `?event_id= &fighter_id= &method= &weight_class= &is_title_bout=` |
| `GET` | `/v1/fights/{id}` | **Full fight**: corners, scorecards, totals + per-round breakdown |

Interactive docs (OpenAPI / Swagger + "try it out") live at **`/docs`**.

### Authentication

Data endpoints require a free API key. Register, then send the key as a bearer token:

```bash
# 1. Register
curl -X POST https://your-host/v1/register \
  -H 'Content-Type: application/json' \
  -d '{"name":"Jane Dev","email":"jane@example.com","password":"supersecret"}'
# => { "data": { "api_key": "1|abc...", "rate_tier": "free", ... } }

# 2. Call the API
curl https://your-host/v1/fighters?search=adesanya \
  -H 'Authorization: Bearer 1|abc...'
```

Free tier: **60 requests/min** per key. Exceeding it returns `429` with the error envelope.

### Example: `GET /v1/fights/{id}`

```jsonc
{
  "data": {
    "id": "d83294b031502177",
    "event": { "id": "31e1...", "name": "UFC Fight Night: Fiziev vs. Torres", "date": "2026-06-27" },
    "weight_class": "Middleweight", "is_title_bout": false,
    "scheduled_rounds": 3, "time_format": "3 Rnd (5-5-5)",
    "red":  { "fighter": { "id": "...", "name": "Ikram Aliskerov" }, "outcome": "win" },
    "blue": { "fighter": { "id": "...", "name": "Brunno Ferreira" }, "outcome": "loss" },
    "method": "Decision - Unanimous", "method_detail": null,
    "end_round": 3, "end_time": "5:00", "referee": "Marc Goddard",
    "scorecards": [ { "judge": "David Lethaby", "red": 30, "blue": 27 } ],
    "stats": {
      "red": {
        "total":  { "sig_str": {"landed":47,"attempted":98,"pct":48}, "knockdowns":0,
                    "takedowns": {"landed":5,"attempted":6,"pct":83}, "control_time_sec":533,
                    "targets": {"head":{"landed":32,"attempted":80}, "body":{}, "leg":{}},
                    "positions": {"distance":{}, "clinch":{}, "ground":{}} },
        "rounds": [ { "round":1, "sig_str":{}, "targets":{}, "positions":{} } ]
      },
      "blue": { "total": {}, "rounds": [] }
    }
  }
}
```

### Error envelope

All `/v1` errors share one shape:

```json
{ "error": { "code": "not_found", "message": "The requested resource was not found." } }
```

Codes: `validation_error` (422, includes `details`), `unauthenticated` (401), `not_found` (404),
`rate_limited` (429), `server_error` (500).

## Data source & scraping

All data comes from ufcstats.com. The scraper is a single Laravel app (no headless browser):
HTTP via Guzzle + parsing via `symfony/dom-crawler`.

> **Note on the anti-bot challenge.** ufcstats serves a lightweight JavaScript proof-of-work
> challenge. The client solves it server-side (standard SHA-256) and caches the clearance
> cookie. The scraper identifies itself honestly via User-Agent, rate-limits itself, and runs
> at most daily. This is an *unofficial* project, not affiliated with or endorsed by the UFC or
> Zuffa LLC; respect ufcstats.com and use the data responsibly.

```bash
php artisan ufc:scrape                          # incremental: new + upcoming events
php artisan ufc:scrape --all                    # full historical backfill
php artisan ufc:scrape --event={ufcstats_id}    # a single event
php artisan ufc:scrape --fighter={ufcstats_id}  # a single fighter
```

Schedule the daily incremental run in `routes/console.php`:

```php
Schedule::command('ufc:scrape')->dailyAt('09:00');
```

## Local development

Built for [Laravel Herd](https://herd.laravel.com) (bundled PHP 8.3 + MySQL), which auto-serves
the project at `http://ufc-api.test`.

```bash
git clone https://github.com/mostafa-amine/ufc-api.git && cd ufc-api
composer install
cp .env.example .env && php artisan key:generate

# MySQL (Herd ships one on 127.0.0.1:3306, root / no password)
mysql -h127.0.0.1 -uroot -e "CREATE DATABASE ufc_api CHARACTER SET utf8mb4"
php artisan migrate

php artisan ufc:scrape --event=<id>   # pull some data
# open http://ufc-api.test/docs
```

## Testing

```bash
php artisan test
```

Tests run against a MySQL `ufc_api_test` database. Parsers are covered by fixture-based unit
tests (real ufcstats HTML saved under `tests/fixtures/`); the scrape pipeline and API are
covered by feature tests, including a scrape **idempotency** check.

## Architecture

```
app/Scraping/
  UfcStatsClient.php          HTTP + proof-of-work challenge solver
  Parsers/                    EventList, EventDetail, FightDetail, Fighter
  Support/Parse.php           tolerant field parsers
  Actions/                    idempotent upserts (event / fight / fighter)
  ScrapeUfc.php               orchestrator (sync list, scrape event, refresh fighters)
app/Http/
  Controllers/Api/V1/         events / fighters / fights / register / health
  Resources/                  JSON shaping + derived percentages
app/Models/                   Event, Fighter, Fight, RoundStat, Scorecard, ScrapeRun
```

See [`docs/specs/2026-06-27-ufc-api-design.md`](docs/specs/2026-06-27-ufc-api-design.md) for the
full design.

## License

[MIT](LICENSE). Data belongs to its respective owners; this project does not claim ownership of
UFC data and is not affiliated with the UFC.
