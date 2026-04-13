# NBA Data Ingestion Scripts

The ingestion pipeline consists of independent Python scripts designed to fetch, normalize, and safely upsert NBA-related objects directly into the `oneshot_fantasy` PostgreSQL schema. It depends on `nba_api` to access the NBA's officially exposed endpoints.

All scripts execute safely utilizing idempotent architecture via PostgreSQL `ON CONFLICT` constraints, allowing repetitive execution without duplicating database entries.

## Environment Setup
Make sure the virtual environment is populated and the database connection logic is accessible:
```bash
cd backend/
python -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt # (or directly via your dependency manager)
```
*Make sure your `DATABASE_URL` is configured correctly inside `backend/.env`!*

## Available Ingestion Scripts

It is recommended to run the ingestor scripts directly from the `backend/` directory in the following order:

### 1. `import_nba_teams.py`
Fetches all currently active NBA franchise teams and metadata. Populates the `teams` relational database.
```bash
python ingestion/scripts/import_nba_teams.py
```
**Details**: Aggregates properties such as Team abbreviation, explicit city mappings, and uniquely assigns the `[external_id, sport]` combination natively.

### 2. `import_nba_players.py`
Retrieves all currently active player rosters across the teams globally and associates each directly under Symfony's `Participant` entity.
```bash
python ingestion/scripts/import_nba_players.py
```
**Details**: Connects a `Participant` to their respective `Team` seamlessly utilizing PostgreSQL explicit foreign keys referencing records previously populated by the `import_nba_teams` script.

### 3. `import_nba_stats.py`
Loads explicit full-season categorical player statistics mapping uniquely to native `StatDefinition` rows inside the generic `participants_stats` architecture.
```bash
python ingestion/scripts/import_nba_stats.py
```
**Details**: Requires the base `StatDefinitionFixture` loaded inside the Symfony app first (`php bin/console doctrine:fixtures:load --group=stats --append`) before properly attributing property categories such as points, assists, rebounds, or percentages mapped safely against specific participants.

### 4. `import_nba_games.py`
Abstracts active scheduled matches or completed playoff games directly into unique atomic objects explicitly resolving `homeTeam` vs `awayTeam` matchups, dates, and finalized score values natively across the atomic `Game` layout.
```bash
python ingestion/scripts/import_nba_games.py
```
**Details**: Cross queries explicit team UUIDs bounding matchup structures securely. Will map game winners automatically resolving explicit point discrepancies to their associated `Team` instance configurations!

### 5. `import_nba_game_stats.py`
Reads detailed boxed statistical results over any atomic game record generated previously and assigns distinct `ParticipantGameStats` mapping dependencies uniquely between granular definitions (`points`, `minutes_played`, `plus_minus`, etc). 
```bash
python ingestion/scripts/import_nba_game_stats.py
```
**Details**: Iterates `LeagueGameLogs` allowing rapid iteration over entire schedule box scores—preventing standard API request-rate limiting natively while inserting distinct categorical statistics individually bound safely per `participant_id`, `game_id`, and `stat_definition_id`. Run this *after* `import_nba_games` and `import_nba_players`.
