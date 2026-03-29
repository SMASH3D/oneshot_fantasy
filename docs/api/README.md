# REST API — MVP specification

**Base path:** `/api/v1`  
**Format:** JSON  
**IDs:** UUIDs as strings in JSON bodies and path segments  

**Authentication (MVP):** Not specified here. Until auth exists, examples may use explicit `user_id` or `league_membership_id` where ownership matters.

**Errors:** `422` for validation (API Platform / Symfony); use `404` for missing resources; `409` with `{"detail":"…","code":"…"}` for conflicts (e.g. duplicate pick, use-once violation).

**Timestamps:** ISO-8601 UTC (e.g. `2026-06-10T18:00:00Z`).

**Implementation status:** HTTP is served by **Symfony + API Platform** (`symfony/`). This file is the **target** MVP contract; live coverage is listed at `/api/docs` (default dev: http://127.0.0.1:8080/api/docs). Liveness without API prefix: `GET /health`.

---

## Summary table

| Area | Method | Route |
|------|--------|--------|
| Health | `GET` | `/health` |
| Users | `POST` | `/users` |
| Users | `GET` | `/users/{user_id}` |
| Tournament | `GET` | `/tournaments` |
| Tournament | `POST` | `/tournaments` |
| Tournament | `GET` | `/tournaments/{tournament_id}` |
| Tournament | `GET` | `/tournaments/{tournament_id}/rounds` |
| Tournament | `GET` | `/tournaments/{tournament_id}/participants` |
| Tournament | `GET` | `/tournaments/{tournament_id}/matches` |
| Leagues | `POST` | `/tournaments/{tournament_id}/leagues` |
| Leagues | `GET` | `/tournaments/{tournament_id}/leagues` |
| Leagues | `GET` | `/leagues` |
| Leagues | `GET` | `/leagues/{league_id}` |
| Leagues | `POST` | `/leagues/{league_id}/members` |
| Leagues | `GET` | `/leagues/{league_id}/members` |
| Leagues | `GET` | `/leagues/{league_id}/rounds` |
| Draft | `POST` | `/leagues/{league_id}/draft/configure` |
| Draft | `POST` | `/leagues/{league_id}/draft/start` |
| Draft | `GET` | `/leagues/{league_id}/draft` |
| Draft | `POST` | `/leagues/{league_id}/draft/picks` |
| Draft | `POST` | `/leagues/{league_id}/draft/complete` |
| Lineup | `GET` | `/leagues/{league_id}/rounds/{fantasy_round_id}/lineups/{membership_id}` |
| Lineup | `PUT` | `/leagues/{league_id}/rounds/{fantasy_round_id}/lineups/{membership_id}` |
| Lineup | `POST` | `/leagues/{league_id}/rounds/{fantasy_round_id}/lineups/{membership_id}/submit` |
| Scores | `GET` | `/leagues/{league_id}/rounds/{fantasy_round_id}/scores` |
| Scores | `GET` | `/leagues/{league_id}/scores` |
| Scores | `POST` | `/leagues/{league_id}/rounds/{fantasy_round_id}/scores/recompute` |

---

## Health

### `GET /health`

**Implemented (Symfony):** liveness only — used by `make healthcheck` and load balancers.

**Response `200`**

```json
{ "status": "ok" }
```

**Target (future):** optional read/write DB probes and structured `checks` object; **`503`** when any check has `"status": "error"`.

```json
{
  "checks": {
    "backend": {
      "status": "ok",
      "message": "HTTP application process is handling requests",
      "latency_ms": null
    },
    "db_read": {
      "status": "ok",
      "message": "Database read: SELECT 1 returned expected result",
      "latency_ms": 2.1
    },
    "db_write": {
      "status": "ok",
      "message": "Database write: INSERT into a session temp table succeeded (table uses ON COMMIT DROP; no persistent data)",
      "latency_ms": 1.4
    }
  }
}
```

---

## Users (supporting)

### `POST /users`

**Request**

```json
{
  "email": "you@example.com",
  "display_name": "Tommy"
}
```

**Response `201`**

```json
{
  "id": "b8c9d0e1-0007-4000-8000-000000000007",
  "email": "you@example.com",
  "display_name": "Tommy"
}
```

### `GET /users/{user_id}`

**Response `200`**

```json
{
  "id": "b8c9d0e1-0007-4000-8000-000000000007",
  "email": "you@example.com",
  "display_name": "Tommy"
}
```

---

## Tournament

### `GET /tournaments`

**Response `200`**

```json
[
  {
    "id": "b2c3d4e5-0001-4000-8000-000000000001",
    "name": "2026 World Cup",
    "slug": "wc-2026",
    "sport_key": "soccer_fifa",
    "status": "scheduled",
    "timezone": "UTC",
    "starts_at": "2026-06-01T00:00:00Z",
    "ends_at": null
  }
]
```

### `POST /tournaments`

**Request**

```json
{
  "name": "2026 World Cup",
  "slug": "wc-2026",
  "sport_key": "soccer_fifa",
  "stats_adapter_key": "noop",
  "default_scoring_engine_key": "simple_sum_v1",
  "timezone": "UTC",
  "starts_at": "2026-06-01T00:00:00Z",
  "ends_at": null,
  "metadata": { "host_nation": "USA" }
}
```

**Response `201`**

```json
{
  "id": "b2c3d4e5-0001-4000-8000-000000000001",
  "name": "2026 World Cup",
  "slug": "wc-2026",
  "sport_key": "soccer_fifa",
  "stats_adapter_key": "noop",
  "default_scoring_engine_key": "simple_sum_v1",
  "status": "draft",
  "timezone": "UTC",
  "starts_at": "2026-06-01T00:00:00Z",
  "ends_at": null,
  "metadata": { "host_nation": "USA" }
}
```

### `GET /tournaments/{tournament_id}`

**Response `200`**

```json
{
  "id": "b2c3d4e5-0001-4000-8000-000000000001",
  "name": "2026 World Cup",
  "slug": "wc-2026",
  "sport_key": "soccer_fifa",
  "stats_adapter_key": "noop",
  "default_scoring_engine_key": "simple_sum_v1",
  "status": "scheduled",
  "timezone": "UTC",
  "starts_at": "2026-06-01T00:00:00Z",
  "ends_at": null,
  "metadata": {}
}
```

### `GET /tournaments/{tournament_id}/rounds`

**Response `200`**

```json
[
  {
    "id": "c3d4e5f6-0002-4000-8000-000000000002",
    "tournament_id": "b2c3d4e5-0001-4000-8000-000000000001",
    "order_index": 0,
    "name": "Round of 16",
    "canonical_key": "R16",
    "metadata": {}
  }
]
```

### `GET /tournaments/{tournament_id}/participants`

**Response `200`**

```json
[
  {
    "id": "d4e5f6a7-0003-4000-8000-000000000003",
    "tournament_id": "b2c3d4e5-0001-4000-8000-000000000001",
    "external_ref": "team-esp-01",
    "display_name": "Spain",
    "kind": "nation",
    "metadata": { "group": "B" }
  }
]
```

### `GET /tournaments/{tournament_id}/matches`

Query: `?tournament_round_id={uuid}` (optional filter).

**Response `200`**

```json
[
  {
    "id": "e5f6a7b8-0004-4000-8000-000000000004",
    "tournament_round_id": "c3d4e5f6-0002-4000-8000-000000000002",
    "scheduled_at": "2026-06-10T18:00:00Z",
    "status": "scheduled",
    "slots": [
      { "slot_index": 0, "participant_id": "d4e5f6a7-0003-4000-8000-000000000003" },
      { "slot_index": 1, "participant_id": null }
    ]
  }
]
```

---

## Leagues

### `GET /leagues`

List all fantasy leagues. Optional query: `tournament_id` (UUID) to restrict to one tournament (same rows as `GET /tournaments/{tournament_id}/leagues`).

**Response `200`**

```json
[
  {
    "id": "a7b8c9d0-0006-4000-8000-000000000006",
    "tournament_id": "b2c3d4e5-0001-4000-8000-000000000001",
    "name": "Office pool",
    "status": "draft",
    "member_count": 4
  }
]
```

### `POST /tournaments/{tournament_id}/leagues`

**Request**

```json
{
  "name": "Office pool",
  "commissioner_user_id": "f6a7b8c9-0005-4000-8000-000000000005",
  "settings": { "max_teams": 8, "roster_size": 15 },
  "lineup_template": [
    { "role": "starter_1", "label": "Starter 1" },
    { "role": "starter_2", "label": "Starter 2" }
  ]
}
```

**Response `201`**

```json
{
  "id": "a7b8c9d0-0006-4000-8000-000000000006",
  "tournament_id": "b2c3d4e5-0001-4000-8000-000000000001",
  "name": "Office pool",
  "commissioner_user_id": "f6a7b8c9-0005-4000-8000-000000000005",
  "status": "forming",
  "settings": { "max_teams": 8, "roster_size": 15 },
  "lineup_template": [
    { "role": "starter_1", "label": "Starter 1" },
    { "role": "starter_2", "label": "Starter 2" }
  ]
}
```

### `GET /tournaments/{tournament_id}/leagues`

**Response `200`**

```json
[
  {
    "id": "a7b8c9d0-0006-4000-8000-000000000006",
    "tournament_id": "b2c3d4e5-0001-4000-8000-000000000001",
    "name": "Office pool",
    "status": "draft",
    "member_count": 4
  }
]
```

### `GET /leagues/{league_id}`

**Response `200`**

```json
{
  "id": "a7b8c9d0-0006-4000-8000-000000000006",
  "tournament_id": "b2c3d4e5-0001-4000-8000-000000000001",
  "name": "Office pool",
  "commissioner_user_id": "f6a7b8c9-0005-4000-8000-000000000005",
  "status": "draft",
  "settings": { "max_teams": 8, "roster_size": 15 },
  "lineup_template": [{ "role": "starter_1", "label": "Starter 1" }]
}
```

### `POST /leagues/{league_id}/members`

**Request**

```json
{
  "user_id": "b8c9d0e1-0007-4000-8000-000000000007",
  "nickname": "Tommy"
}
```

**Response `201`**

```json
{
  "id": "c9d0e1f2-0008-4000-8000-000000000008",
  "league_id": "a7b8c9d0-0006-4000-8000-000000000006",
  "user_id": "b8c9d0e1-0007-4000-8000-000000000007",
  "nickname": "Tommy",
  "role": "member"
}
```

### `GET /leagues/{league_id}/members`

**Response `200`**

```json
[
  {
    "id": "c9d0e1f2-0008-4000-8000-000000000008",
    "user_id": "b8c9d0e1-0007-4000-8000-000000000007",
    "nickname": "Tommy",
    "role": "member"
  }
]
```

### `GET /leagues/{league_id}/rounds`

Fantasy scoring periods.

**Response `200`**

```json
[
  {
    "id": "d0e1f2a3-0009-4000-8000-000000000009",
    "league_id": "a7b8c9d0-0006-4000-8000-000000000006",
    "tournament_round_id": "c3d4e5f6-0002-4000-8000-000000000002",
    "order_index": 0,
    "name": "Round of 16",
    "opens_at": "2026-06-09T00:00:00Z",
    "locks_at": "2026-06-10T17:00:00Z",
    "metadata": {}
  }
]
```

---

## Draft

### `POST /leagues/{league_id}/draft/configure`

**Request**

```json
{
  "snake": true,
  "pick_time_seconds": 120,
  "order_membership_ids": [
    "c9d0e1f2-0008-4000-8000-000000000008",
    "e1f2a3b4-000a-4000-8000-00000000000a"
  ]
}
```

**Response `200`**

```json
{
  "league_id": "a7b8c9d0-0006-4000-8000-000000000006",
  "draft_session_id": "f2a3b4c5-000b-4000-8000-00000000000b",
  "status": "pending",
  "config": {
    "snake": true,
    "pick_time_seconds": 120,
    "order_membership_ids": [
      "c9d0e1f2-0008-4000-8000-000000000008",
      "e1f2a3b4-000a-4000-8000-00000000000a"
    ]
  }
}
```

### `POST /leagues/{league_id}/draft/start`

**Response `200`**

```json
{
  "draft_session_id": "f2a3b4c5-000b-4000-8000-00000000000b",
  "status": "in_progress",
  "current_pick_index": 0,
  "deadline_at": "2026-06-08T12:02:00Z"
}
```

### `GET /leagues/{league_id}/draft`

**Response `200`**

```json
{
  "draft_session_id": "f2a3b4c5-000b-4000-8000-00000000000b",
  "status": "in_progress",
  "current_pick_index": 3,
  "picks": [
    {
      "pick_index": 0,
      "league_membership_id": "c9d0e1f2-0008-4000-8000-000000000008",
      "participant_id": "d4e5f6a7-0003-4000-8000-000000000003"
    }
  ]
}
```

### `POST /leagues/{league_id}/draft/picks`

**Request**

```json
{
  "league_membership_id": "c9d0e1f2-0008-4000-8000-000000000008",
  "participant_id": "d4e5f6a7-0003-4000-8000-000000000003"
}
```

**Response `201`**

```json
{
  "pick_index": 3,
  "league_membership_id": "c9d0e1f2-0008-4000-8000-000000000008",
  "participant_id": "d4e5f6a7-0003-4000-8000-000000000003"
}
```

### `POST /leagues/{league_id}/draft/complete`

**Response `200`**

```json
{
  "draft_session_id": "f2a3b4c5-000b-4000-8000-00000000000b",
  "status": "completed",
  "league_status": "active"
}
```

---

## Lineup

### `GET /leagues/{league_id}/rounds/{fantasy_round_id}/lineups/{membership_id}`

**Response `200`**

```json
{
  "fantasy_round_id": "d0e1f2a3-0009-4000-8000-000000000009",
  "league_membership_id": "c9d0e1f2-0008-4000-8000-000000000008",
  "status": "draft",
  "slots": [
    {
      "order_index": 0,
      "slot_role": "starter_1",
      "participant_id": "d4e5f6a7-0003-4000-8000-000000000003"
    },
    { "order_index": 1, "slot_role": "starter_2", "participant_id": null }
  ]
}
```

### `PUT /leagues/{league_id}/rounds/{fantasy_round_id}/lineups/{membership_id}`

**Request**

```json
{
  "slots": [
    {
      "order_index": 0,
      "slot_role": "starter_1",
      "participant_id": "d4e5f6a7-0003-4000-8000-000000000003"
    },
    {
      "order_index": 1,
      "slot_role": "starter_2",
      "participant_id": "e5f6a7b8-0004-4000-8000-000000000004"
    }
  ]
}
```

**Response `200`**

```json
{
  "fantasy_round_id": "d0e1f2a3-0009-4000-8000-000000000009",
  "league_membership_id": "c9d0e1f2-0008-4000-8000-000000000008",
  "status": "draft",
  "slots": [
    {
      "order_index": 0,
      "slot_role": "starter_1",
      "participant_id": "d4e5f6a7-0003-4000-8000-000000000003"
    },
    {
      "order_index": 1,
      "slot_role": "starter_2",
      "participant_id": "e5f6a7b8-0004-4000-8000-000000000004"
    }
  ]
}
```

### `POST /leagues/{league_id}/rounds/{fantasy_round_id}/lineups/{membership_id}/submit`

**Response `200`**

```json
{
  "status": "locked",
  "submitted_at": "2026-06-10T16:55:00Z"
}
```

---

## Scores

### `GET /leagues/{league_id}/rounds/{fantasy_round_id}/scores`

**Response `200`**

```json
{
  "fantasy_round_id": "d0e1f2a3-0009-4000-8000-000000000009",
  "rows": [
    {
      "league_membership_id": "c9d0e1f2-0008-4000-8000-000000000008",
      "nickname": "Tommy",
      "points": 42.5,
      "rank": 1,
      "breakdown": { "starter_1": 30, "starter_2": 12.5 }
    }
  ]
}
```

### `GET /leagues/{league_id}/scores`

Cumulative leaderboard.

**Response `200`**

```json
{
  "league_id": "a7b8c9d0-0006-4000-8000-000000000006",
  "rows": [
    {
      "league_membership_id": "c9d0e1f2-0008-4000-8000-000000000008",
      "nickname": "Tommy",
      "points": 128.25,
      "rank": 1
    }
  ]
}
```

### `POST /leagues/{league_id}/rounds/{fantasy_round_id}/scores/recompute`

**Request**

```json
{
  "membership_ids": null
}
```

(`null` = all members.)

**Response `202`**

```json
{
  "status": "accepted",
  "fantasy_round_id": "d0e1f2a3-0009-4000-8000-000000000009"
}
```
