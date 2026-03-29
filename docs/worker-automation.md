# Python worker automation (cron / ops)

The worker runs **async** jobs against PostgreSQL using `DATABASE_URL` in `backend/.env` (`postgresql+asyncpg://…`).

## Scripts

| Script | Role |
|--------|------|
| `python -m scripts.sync_data` | Ingest `stat_events` for one tournament (adapter + cursor). |
| `python -m scripts.compute_scores` | Recompute `round_scores` for one league + fantasy round. |
| `python -m scripts.score_batch` | Same as scoring, driven by a **jobs file** (multiple league/round pairs). |

Common flags:

- **`-v` / `--verbose`**: DEBUG logs on stderr.
- All structured logs use **UTC** timestamps on **stderr** (stdout stays free for piping).

## Idempotency and safe re-runs

### Scoring (`compute_scores`, `score_batch`)

- **Idempotent:** each run **overwrites** the `round_scores` row for `(fantasy_round_id, league_membership_id)` with a fresh total and breakdown.
- Safe to run on a schedule (e.g. every 15 minutes) while stats are still arriving; results converge as `stat_events` grow.

### Data sync (`sync_data`)

- **Incremental by cursor:** the adapter returns `(events, next_cursor)`. After a successful transaction, `next_cursor` is stored in `tournaments.metadata.ingestion_cursor`.
- Re-running with the **same** cursor semantics should **not** re-fetch already processed pages (depends on the adapter implementation).
- **`noop`** adapter always returns no events: harmless to run on a timer.
- **Real adapters** must avoid emitting duplicate logical events across runs, or you should add DB-level deduplication (e.g. unique constraint + `ON CONFLICT DO NOTHING`) in a future migration.

### Transactions

- Each script uses **one transaction** per job (`session.begin()`): failure rolls back that job’s writes (including cursor updates for sync).

## Logging strategy

1. **Cron default:** redirect stderr to a file or syslog; keep exit codes for monitoring.

   ```bash
   cd /path/to/oneshot_fantasy/backend && \
   .venv/bin/python -m scripts.sync_data --tournament-id "$TID" \
     >>/var/log/oneshot/sync.log 2>&1
   ```

2. **Structured fields:** log lines are `LEVEL [logger] message` with ISO-8601 UTC time — easy to grep (`grep ERROR`, `grep sync_data`).

3. **Retention:** rotate logs with **logrotate** (see example snippet below).

4. **Alerting:** watch **non-zero exit code**; optional: scrape last line for `inserted=0` if you need “no data” alerts (adapter-specific).

## Concurrency: single-flight (`flock`)

Avoid overlapping runs (duplicate load, cursor races) with **`flock`**:

```bash
flock -n /tmp/oneshot-sync.lock \
  bash -c 'cd /path/to/oneshot_fantasy/backend && .venv/bin/python -m scripts.sync_data --tournament-id YOUR-TOURNAMENT-UUID' \
  >>/var/log/oneshot/sync.log 2>&1
```

`-n` = non-blocking; if another run holds the lock, cron skips this invocation (exit non-zero — tune mail/wrapper if needed).

Use a **different** lock file for scoring vs sync.

## Cron examples

Adjust paths, users, and UUIDs. Prefer a dedicated OS user with read-only filesystem except logs.

### `/etc/cron.d/oneshot-fantasy` (root or app user)

```cron
SHELL=/bin/bash
PATH=/usr/local/bin:/usr/bin:/bin

# Stat ingest every hour (single tournament)
15 * * * * deploy flock -n /tmp/oneshot-sync.lock bash -c 'cd /srv/oneshot_fantasy/backend && . .venv/bin/activate && python -m scripts.sync_data --tournament-id 00000000-0000-4000-8000-000000000001' >>/var/log/oneshot/sync.log 2>&1

# Scoring every 20 minutes after sync (batch file lists league + fantasy round)
35 * * * * deploy flock -n /tmp/oneshot-score.lock bash -c 'cd /srv/oneshot_fantasy/backend && . .venv/bin/activate && python -m scripts.score_batch --jobs-file /etc/oneshot/score-jobs.txt --continue-on-error' >>/var/log/oneshot/score.log 2>&1
```

### Jobs file for `score_batch` (`/etc/oneshot/score-jobs.txt`)

```text
# league_id fantasy_round_id
a7b8c9d0-0006-4000-8000-000000000006  d0e1f2a3-0009-4000-8000-000000000009
```

### logrotate (`/etc/logrotate.d/oneshot-fantasy`)

```text
/var/log/oneshot/*.log {
    weekly
    rotate 12
    compress
    delaycompress
    missingok
    notifempty
    copytruncate
}
```

## Makefile (local / CI smoke)

From the repo root:

```bash
make worker-sync TOURNAMENT_ID=<uuid>
make worker-score LEAGUE_ID=<uuid> FANTASY_ROUND_ID=<uuid>
```

## Systemd (optional)

For dependency-aware scheduling and journald, use **`OnCalendar=`** timers instead of cron; keep the same `flock` + `WorkingDirectory=` + `EnvironmentFile=/srv/oneshot_fantasy/backend/.env` pattern.

## Best practices checklist

- [ ] Load `DATABASE_URL` from **env file** or secrets manager — never commit credentials.
- [ ] Use **`flock`** (or systemd `Mutex`) so sync jobs do not overlap.
- [ ] Point logs to rotated files or **journald**; alert on **exit code ≠ 0**.
- [ ] Run scoring **after** sync in the same hour if stats are time-sensitive (stagger cron minutes).
- [ ] Use **`score_batch --continue-on-error`** in production so one bad league does not block others (monitor logs for failures).
