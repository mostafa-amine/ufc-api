# Unofficial UFC API — Design Spec

**Date:** 2026-06-27
**Status:** Approved (build in progress)
**Source of truth:** [ufcstats.com](http://ufcstats.com) (server-rendered static HTML)

## 1. Goal & positioning

A **hosted, open-source (MIT) REST API** that exposes the *full* depth of ufcstats.com
data — including the parts every existing project drops — through a clean, documented,
queryable interface.

### Why this exists (the gap)
The competitive landscape splits in two:
- **Detailed datasets** (e.g. `Greco1899/scrape_ufc_stats`, `komaksym/UFC-DataLab`) capture
  most of ufcstats' depth — round-by-round stats, even judge scorecards — but ship as **CSV
  dumps**, not an API.
- **APIs** (e.g. `telman03/ufc` Go, `fight-api/ufc-fight-api` Django) are **shallow** —
  fighters + rankings only, no round-by-round depth, thinly documented.

ufcstats is the hard ceiling on data detail; nobody can be "more detailed" than the source.
**The real gap is turning that depth into a well-built, documented, queryable hosted API.**
That is this project's wedge — not "more data", but "the detailed data, finally queryable".

### Differentiators
- Full round-by-round stats **exposed relationally and filterable**.
- Significant-strike breakdown by **target** (head/body/leg) and **position**
  (distance/clinch/ground), per round.
- **Judge scorecards** — the field most competitors drop.
- Fight metadata: referee, method detail, time format, bout order, title-bout flag.
- Stable IDs cross-referenceable back to ufcstats.

## 2. Locked decisions

| Decision | Choice |
|---|---|
| Product shape | Hosted public REST API |
| License | Open source (MIT) |
| Data sources | **ufcstats.com only** (no ufc.com rankings/photos, no odds — deferred) |
| Stack | **All-Laravel** (scraper + API + scheduler in one codebase) |
| PHP / Framework | PHP 8.3, Laravel 13 |
| Database | **MySQL 8** (`utf8mb4`) |
| Local dev | **Laravel Herd** (auto-serves `ufc-api.test`, bundled MySQL) |
| Scraping | Laravel HTTP client (Guzzle) + `symfony/dom-crawler` + `symfony/css-selector` |
| Freshness | **Daily batch**, incremental, + manual `php artisan ufc:scrape` |
| Access | Free **API keys** (Sanctum) + per-key rate limiting; paid tier deferred |
| Docs | Scribe (OpenAPI / Swagger UI) |
| Coverage | Full historical backfill (1994 → present) |
| Testing | Pest, fixture-driven parser unit tests + API feature tests |

## 3. Data model (normalized — "Approach A")

Percentages are **derived in the API layer**, never stored (single source of truth =
`landed`/`attempted`). Messy source fields store both a parsed value **and** the raw string
(`*_raw`) so a parse bug never loses data. Every table carries the **ufcstats hex ID** as a
unique natural key for bulletproof idempotent upserts.

### `events`
- `id` PK · `ufcstats_id` CHAR(16) UNIQUE · `name` · `date` (nullable for TBD)
- `location_raw` · `city?` · `state?` · `country?`
- `status` ENUM(`upcoming`,`completed`) · `url`
- `last_scraped_at?` · timestamps

### `fighters`
- `id` PK · `ufcstats_id` CHAR(16) UNIQUE · `name` · `nickname?`
- `height_in?` · `height_raw?` · `weight_lb?` · `weight_raw?` · `reach_in?` · `reach_raw?`
- `stance?` · `dob?` · `dob_raw?`
- record: `wins` · `losses` · `draws` · `no_contests`
- career averages (nullable): `slpm` · `str_acc` · `sapm` · `str_def` · `td_avg` · `td_acc`
  · `td_def` · `sub_avg`
- `url` · `last_scraped_at?` · timestamps
- FULLTEXT(`name`,`nickname`)

### `fights`
- `id` PK · `ufcstats_id` CHAR(16) UNIQUE · `event_id` FK · `bout_order` (smallint; 1 = top of card)
- `weight_class?` · `is_title_bout` bool · `scheduled_rounds?` · `time_format_raw?`
- `red_fighter_id` FK · `blue_fighter_id` FK
- `winner_fighter_id?` FK (null = draw / NC / not yet fought)
- `outcome` ENUM(`win`,`draw`,`nc`,`dq`,`overturned`,`pending`)
- `method?` · `method_detail?` · `end_round?` · `end_time_sec?` · `referee?`
- timestamps · INDEX(`event_id`), INDEX(`red_fighter_id`), INDEX(`blue_fighter_id`)

### `round_stats` — the detailed core
One row per (fight, fighter, round). **`round = 0` is the fight total** (mirrors ufcstats'
"Totals" table); `round >= 1` are per-round rows. Storing the source's own total lets us
sanity-check `SUM(rounds) == total` as a parse-integrity guard.
- `fight_id` FK · `fighter_id` FK · `round` TINYINT — UNIQUE(`fight_id`,`fighter_id`,`round`)
- `knockdowns`
- `sig_str_landed` · `sig_str_attempted`
- `total_str_landed` · `total_str_attempted`
- `takedowns_landed` · `takedowns_attempted`
- `sub_attempts` · `reversals` · `control_time_sec?`
- targets: `head_landed`/`head_attempted` · `body_landed`/`body_attempted` · `leg_landed`/`leg_attempted`
- positions: `distance_landed`/`distance_attempted` · `clinch_landed`/`clinch_attempted` · `ground_landed`/`ground_attempted`

> Pre-2010-ish and some early fights lack per-round breakdowns; those rows may be totals-only
> or absent. The schema tolerates this (all stat columns nullable / default 0 + presence flags
> via existence of rows).

### `scorecards`
Decisions only (empty for finishes).
- `id` PK · `fight_id` FK · `judge_name` · `red_score` · `blue_score` · timestamps

### Auth / plumbing
- `users` (Laravel default) + `rate_tier` column (default `free`)
- Sanctum `personal_access_tokens`
- `scrape_runs`: `id` · `type` (`incremental`/`full`/`event`) · `started_at` · `finished_at?`
  · `status` (`running`/`success`/`failed`) · `events_scraped` · `fights_scraped`
  · `fighters_scraped` · `errors` (JSON) · timestamps

## 4. API surface (`/v1`)

Public resource IDs = the **ufcstats hex ID** (stable, cross-referenceable); internal bigint
hidden. Route-model binding on `ufcstats_id`.

```
Auth
  POST /v1/register                 -> { name, email, password } => issues API key

Events
  GET  /v1/events                   ?status= &from= &to= &search= &page= &per_page= &sort=-date
  GET  /v1/events/{id}              event + bouts (summary)

Fighters
  GET  /v1/fighters                 ?search= &stance= &page= &sort=name
  GET  /v1/fighters/{id}            full profile + career averages
  GET  /v1/fighters/{id}/fights     bout history (paginated)

Fights
  GET  /v1/fights                   ?event_id= &fighter_id= &method= &weight_class= &is_title_bout= &page=
  GET  /v1/fights/{id}              FULL detail: corners, result, scorecards,
                                    round-by-round + totals, sig-strike target/position breakdown

Meta
  GET  /v1/health
  GET  /docs                        Scribe OpenAPI / Swagger UI
```

### Conventions
- All list endpoints: pagination (`data` + `meta`/`links`), `?per_page` capped at 100,
  `?sort` and filters **whitelisted** (FormRequest validation).
- Auth: `Authorization: Bearer <key>`; throttle `60/min` (free tier) with `X-RateLimit-*` headers.
- **Derived percentages** (`sig_str_pct`, `td_pct`, …) computed in API Resources.
- Errors: consistent JSON `{ "error": { "code", "message", "details"? } }`; correct status
  codes (404 / 422 / 429). `/v1/*` exceptions render as JSON.

### `GET /v1/fights/{id}` response shape (the crown jewel)
```jsonc
{
  "data": {
    "id": "abc123…", "event": { "id": "…", "name": "…", "date": "…" },
    "weight_class": "Lightweight", "is_title_bout": false,
    "scheduled_rounds": 3, "time_format": "3 Rnd (5-5-5)",
    "red":  { "fighter": { "id": "…", "name": "…" }, "outcome": "win" },
    "blue": { "fighter": { "id": "…", "name": "…" }, "outcome": "loss" },
    "method": "KO/TKO", "method_detail": "Punches",
    "end_round": 2, "end_time": "4:10", "referee": "Herb Dean",
    "scorecards": [ { "judge": "Sal D'Amato", "red": 0, "blue": 0 } ],
    "stats": {
      "red": {
        "total":  { "sig_str": {"landed":47,"attempted":81,"pct":58},
                    "knockdowns":1, "takedowns": {"landed":0,"attempted":1,"pct":0},
                    "sub_attempts":0, "reversals":0, "control_time_sec":210 },
        "rounds": [ { "round":1,
                      "sig_str": {"landed":22,"attempted":40,"pct":55},
                      "head": {"landed":15,"attempted":30}, "body": {"landed":4,"attempted":6}, "leg": {"landed":3,"attempted":4},
                      "distance": {"landed":18,"attempted":34}, "clinch": {"landed":2,"attempted":3}, "ground": {"landed":2,"attempted":3},
                      "takedowns": {"landed":0,"attempted":1}, "control_time_sec":95 } ]
      },
      "blue": { "total": { … }, "rounds": [ … ] }
    }
  }
}
```

## 5. Scraper & freshness design

### Source pages
| Purpose | URL |
|---|---|
| Completed events | `http://ufcstats.com/statistics/events/completed?page=all` |
| Upcoming events | `http://ufcstats.com/statistics/events/upcoming?page=all` |
| Event detail (bouts) | `http://ufcstats.com/event-details/{id}` |
| Fight detail (stats) | `http://ufcstats.com/fight-details/{id}` |
| Fighter detail | `http://ufcstats.com/fighter-details/{id}` |

### Anti-bot challenge (discovered during build)
ufcstats now gates every page behind a lightweight JavaScript **proof-of-work** challenge
("Checking your browser…"), so the original "100% static HTML" assumption is only true *after*
clearance. The challenge is self-contained and standard SHA-256:
1. The page embeds a `nonce` and a difficulty (`target` = N leading hex zeros, currently 2).
2. Find `n` such that `sha256(nonce + ":" + n)` starts with N zeros (~256 tries; trivial).
3. `POST /__c` with `nonce` + `n`; the response sets a clearance cookie. Subsequent requests
   with that cookie return real HTML.

This is solvable **server-side in pure PHP** (no headless browser), so the lightweight
Laravel-only design holds. `UfcStatsClient` solves it transparently on a challenge response,
persists the clearance cookie (cache), and reuses it across requests, re-solving only when it
expires. Verified end-to-end during M2.

### Scorecard ordering (discovered + verified, 9 samples)
ufcstats prints each judge's score as `a - b` where the **second number is the bout winner's
corner** — *not* a fixed red/blue position. Confirmed across unanimous + split decisions and
both winner corners. The parser maps scores to corners via the bout winner; naive `[red, blue]`
positional mapping (used by most scrapers) corrupts ~half of all scorecards. Draws/NC fall back
to document order (`first → red`, `second → blue`) and are documented as best-effort.

### Components
- **`UfcStatsClient`** — polite HTTP: solves the PoW challenge + caches clearance cookie,
  configurable concurrency (default 4), retry w/ backoff on 5xx/timeout, descriptive
  User-Agent, small jittered delay. Optional on-disk HTML cache for replay/debug.
- **Parsers** (one per page type) — pure: `string $html -> DTO`. `EventListParser`,
  `EventDetailParser`, `FightDetailParser`, `FighterParser`. Use DomCrawler + CSS selectors.
  Fully unit-testable against saved HTML fixtures.
- **`Parse` support** — field helpers: height `5' 11"` → inches, weight `155 lbs.` → int,
  reach `72.0"` → float, DOB `Jan 14, 1992` → date, record `24-1-0 (1 NC)` → counts,
  `47 of 81` → (landed, attempted), control `2:10` → 130s, `--` → null. Tolerant by design.
- **Upsert actions** — `updateOrCreate` keyed on `ufcstats_id`; idempotent.
- **Jobs** — `ScrapeEventJob`, `ScrapeFighterJob` (queued for backfill; rate-limited queue).
- **`ufc:scrape` command** — orchestrator. Flags: `--all` (full backfill via queue),
  `--event={id}`, `--refresh` (re-scrape completed), default = incremental.

### Pipeline (incremental, default daily)
1. Fetch event list pages → upsert `events` (new ids discovered, status set).
2. Select events to (re)scrape: `upcoming`, OR `completed` with bouts missing stats, OR never
   scraped. Skip already-complete events unless `--refresh`.
3. Per selected event → fetch event detail → upsert `fights` (shells: corners, result,
   method, round/time, weight class, title flag, bout order).
4. Per fight needing stats → fetch fight detail → upsert `round_stats` (totals `round=0` +
   per-round) and `scorecards`.
5. Collect referenced fighter ids → fetch fighter detail → upsert `fighters` + career stats +
   refresh records.
6. Write a `scrape_runs` row with counts + any per-item errors.

### Idempotency & integrity
- Natural-key upserts ⇒ re-running yields identical DB state.
- After a fight is parsed, assert `SUM(round>=1) == round 0 total` per summable metric; on
  mismatch, log to `scrape_runs.errors` (don't fail the run).

### Politeness & robustness
- Bounded concurrency, jittered delay, retry/backoff; one bad page never aborts the run —
  errors are logged per-item and the run continues.

## 6. Error handling

- **Network:** retry w/ backoff → on final failure, record in `scrape_runs.errors`, continue.
- **Parse:** defensive per-field; unparseable ⇒ store `*_raw`, null the parsed value, log
  warning. Never crash a run on one field.
- **API:** global handler renders `/v1/*` exceptions as JSON; 404 unknown id, 422 validation,
  429 throttle, 500 generic (no stack traces in prod).

## 7. Testing strategy (TDD)

- **Parser unit tests (core):** real ufcstats pages saved to `tests/fixtures/*.html`; assert
  parsed DTOs field-by-field, including edge cases (draw, NC, DQ, decision w/ scorecards,
  finish, missing fields, old fight w/o round breakdown).
- **Scrape integration:** `Http::fake()` serving fixtures → run `ufc:scrape` → assert DB
  state; run twice → assert **idempotent** (no dupes, no drift).
- **API feature tests:** factory-seeded DB → hit each endpoint → assert JSON shape, filters,
  pagination, sorting, auth required, throttle (429), derived percentages.
- Pest + `RefreshDatabase`. Parsers tested against fixtures (no network in tests).

## 8. Project structure

```
app/
  Models/            Event, Fighter, Fight, RoundStat, Scorecard, User, ScrapeRun
  Scraping/
    UfcStatsClient.php
    Parsers/         EventListParser, EventDetailParser, FightDetailParser, FighterParser
    DTOs/            EventData, BoutData, FightDetailData, RoundStatData, FighterData, ScorecardData
    Support/         Parse.php
    Actions/         UpsertEvent, UpsertFight, UpsertFightStats, UpsertFighter
  Jobs/              ScrapeEventJob, ScrapeFighterJob
  Console/Commands/  ScrapeUfcCommand.php   (signature: ufc:scrape)
  Http/
    Controllers/Api/V1/  EventController, FighterController, FightController, RegisterController, HealthController
    Resources/           EventResource, EventSummaryResource, FighterResource, FightResource, FightSummaryResource, RoundStatResource, ScorecardResource
    Requests/            EventIndexRequest, FighterIndexRequest, FightIndexRequest, RegisterRequest
routes/api.php          (v1 group)
database/migrations/    events, fighters, fights, round_stats, scorecards, scrape_runs, users.rate_tier
database/factories/
tests/Unit/Parsers/     + tests/fixtures/*.html
tests/Feature/Api/      + tests/Feature/Scraping/
```

## 9. Build milestones (implementation order)

1. **Schema + models + factories** — migrations for all tables, Eloquent models w/ relations.
2. **Parse helpers + parsers (TDD)** — fixtures first; the riskiest, highest-value core.
3. **Client + scrape orchestration + upserts** — `Http::fake` integration + idempotency tests.
4. **API** — resources, controllers, routes, filters, pagination (feature tests).
5. **Auth + rate limiting + JSON error envelope** — Sanctum register + key, throttle.
6. **Scribe docs + README + MIT LICENSE.**
7. **Backfill run** against live ufcstats + spot-verify.

## 10. Out of scope (v1)

- ufc.com rankings, fighter photos, nationality.
- Betting odds.
- Real-time / event-night live polling (daily batch only).
- Paid tiers / billing (schema is key-ready; flip later, no migration).
- GraphQL (REST only).
