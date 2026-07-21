# 📘 Learning XP

Learning XP is a separate, optional XP pool that mirrors a student's **per-course XP earned in
`block_playerhud`** into PlayerGames, purely for cross-course visibility — it is never summed
with season XP and never affects levels, avatar unlocks or the season ranking.

## Where it comes from

When `block_playerhud` is installed and a student earns or loses course XP, PlayerGames listens
for that change and mirrors the delta into its own **windowed** learning XP total. Nothing needs
to be configured on the `block_playerhud` side beyond having it installed — the bridge is
optional and stays dormant if `block_playerhud` isn't present.

## The window

Learning XP is not a lifetime total: it only counts XP earned within a **rolling window**
(default 12 months, configurable in months; `0` means unlimited/lifetime). Data is kept in
monthly buckets, so the window ages correctly month by month, and a nightly scheduled task
recomputes every user's windowed total to correct any drift and drop buckets that have aged out.

## Visibility

Learning XP is shown only when **both** are true:
- the admin has turned it on (*Site administration → Plugins → Local plugins → PlayerGames →
  Learning XP*), and
- the site's participant setting allows students (`students` or `both`).

Unlike most other student-facing features, visibility is **not** tied to whether the viewer is
flagged as staff — a teacher who is also a genuine PlayerHUD-earning student in another course
still sees their own learning XP.

## Its own ranking

Learning XP has its own **separate, opt-in ranking** — a single top-50 list (not split by
staff/student like the season ranking), gated by its own admin toggle. Players with zero
windowed XP are excluded entirely rather than occupying a rank, and each player has an
independent opt-in toggle from the season ranking's own visibility setting. Position lookup for
players outside the top 50 follows the same tie-break rule as the season ranking (highest XP
first, then earliest to reach it).

## Where it's shown

Both the Player Hub page and the [PlayerGames block](#ecosystem)'s sidebar widget read from the
same manager and follow the same visibility and ranking rules, so the two surfaces are always
consistent with each other.
