"""
Bounded contexts (planned packages):

- leagues: league lifecycle, membership, settings
- draft: snake draft order, picks, player pool
- tournament: bracket rounds, scheduling hooks
- lineup: per-round selections and use-once rules
- scoring: sport-agnostic score calculation (strategy + adapters)
- leaderboard: aggregates and rankings

Implement incrementally; keep HTTP out of these packages.
"""
