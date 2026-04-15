# NBA Data Ingestion Scripts

The ingestion pipeline is a set of independent Python scripts that fetch data from the NBA's
stats API, normalize it, and upsert it into the same PostgreSQL database that Symfony/Doctrine
uses.

## Environment setup

**Always use the Makefile** — it handles venv activation and `.env` loading automatically:

```bash
make install-worker   # Creates backend/.venv and installs all Python dependencies
```

Then make sure `backend/.env` exists with a `DATABASE_URL` pointing at your Postgres instance:

```ini
DATABASE_URL=postgresql://postgres:password@localhost:5432/oneshot_fantasy
```

This is the same database Symfony uses; the Python scripts write directly to shared tables
(`teams`, `participants`, `games`, etc.) using raw SQL via `psycopg2`.

---

## Running scripts

**Always use the Makefile targets** — they activate the venv Python and load `.env` automatically.
Running `python ingestion/scripts/…` with the system Python will fail because dependencies
(psycopg2, nba_api, etc.) are inside `.venv`, not in the system.

```bash
# CORRECT
make ingest-nba-teams
make ingest-nba-players
# etc.

# WRONG — will fail with "No module named psycopg2"
python ingestion/scripts/import_nba_teams.py
```

---

## NBA API data source

All scripts use [`nba_api`](https://github.com/swar/nba_api), a Python wrapper around
`stats.nba.com`. The library provides Python classes for each endpoint; each class fetches JSON
from NBA's servers and returns a pandas DataFrame. **No API key required.**

Limitations to be aware of:
- The NBA can change endpoint shapes or add rate limiting without notice.
- Playoff-specific endpoints (`CommonPlayoffSeries`) return no data until the playoff bracket
  is officially seeded — typically after the play-in tournament ends (late April / early May).
- The library may throttle requests; if you hit rate limits, add a `time.sleep(1)` between calls.

---

## Available ingestion scripts

Run them in this order — each script depends on the previous one having populated its tables.

### 1. `import_nba_teams` — populate `teams`

```bash
make ingest-nba-teams
```

Fetches all active NBA franchises. Populates the `teams` table with:
- `external_id` = NBA team ID (used as the join key by all later scripts)
- `name`, `abbreviation`, `city`, `sport = "basketball"`

**NBA endpoint used:** `TeamInfoCommon` / `CommonTeamYears`
**Re-run safe:** yes — `ON CONFLICT (external_id, sport) DO UPDATE`

---

### 2. `import_nba_players` — populate `participants`

```bash
make ingest-nba
```

Fetches the current season's active roster across all teams. Populates `participants`:
- Links each player to their `team` row via `team_id` (FK to `teams.id`)
- Stores NBA's `PERSON_ID` as `external_id`
- Only imports players with `ROSTERSTATUS = 1` (active)

**NBA endpoint used:** `CommonAllPlayers`
**Requires:** teams imported first
**Re-run safe:** yes

---

### 3. `import_nba_stats` — populate `participant_stats`

```bash
make ingest-nba-stats
```

Loads season-aggregate statistics (points per game, assists, rebounds, etc.) into
`participant_stats`. Each stat is stored as a `(participant_id, stat_definition_id, value)` row.

**NBA endpoint used:** `PlayerCareerStats` / season averages
**Requires:** Symfony stat definition fixtures loaded first:
```bash
cd symfony && php bin/console doctrine:fixtures:load --group=stats --append
```
**Re-run safe:** yes

---

### 4. `import_nba_games` — populate `games`

```bash
make ingest-nba-games
```

Fetches playoff game schedules and results. Populates `games`:
- `external_id` = NBA game ID (10-digit string, e.g. `0042500101`)
- Disambiguates home vs away from the `MATCHUP` field (`"BOS vs. MIA"` = home, `"MIA @ BOS"` = away)
- Sets `status` to `scheduled`, `live`, or `complete` based on whether scores are present

**NBA endpoint used:** `LeagueGameFinder` with `season_type="Playoffs"`
**Requires:** teams imported first
**Re-run safe:** yes

---

### 5. `import_nba_game_stats` — populate per-game player boxscores

```bash
make ingest-nba-game-stats
```

Fetches individual player boxscores for every playoff game. Each row in `participant_game_stats`
records one player's performance in one game: points, assists, rebounds, blocks, steals,
turnovers, minutes played, plus/minus.

**NBA endpoint used:** `LeagueGameLog` (players, playoffs)
**Requires:** games and players imported first
**Re-run safe:** yes

---

### 6. `import_nba_playoffs` — populate `tournament_rounds`

```bash
make ingest-nba-playoffs SLUG=nba-playoffs-2026

# With an explicit season (defaults to current):
make ingest-nba-playoffs SLUG=nba-playoffs-2026 SEASON=2025-26
```

Maps NBA playoff series (First Round, Conference Semifinals, Conference Finals, NBA Finals) to
`tournament_rounds` rows. For each series it:
1. Upserts a `tournament_rounds` row with home/away teams and series status
2. Links existing `games` rows to that round by matching the NBA series prefix in `external_id`

The `SLUG` must match a row in the `tournaments` table (Symfony admin → Tournaments).

**NBA endpoint used:** `CommonPlayoffSeries`
**Requires:** teams and games imported first; tournament row created in Symfony admin
**Re-run safe:** yes — idempotent upsert

#### Why it returns 0 results early in the season

`CommonPlayoffSeries` is populated by the NBA only **after the playoff bracket is officially
set** — typically once the play-in tournament ends (late April / early May). Running it earlier
will always return 0 series. This is a limitation of the NBA's API, not a bug in the script.

---

## Data flow summary

```
stats.nba.com  ──nba_api──▶  Python scripts  ──psycopg2──▶  PostgreSQL  ◀──Doctrine──  Symfony
```

The Python layer is write-only from Symfony's perspective — it only ever inserts/updates rows.
Symfony reads and exposes this data via its API and admin panel. Neither side locks the other out.
