# 🏆 Ranking Behavior

The season ranking is opt-in, split by participant group, and shows a player their own position
even outside the visible top 50.

## Opt-in and the top-50 list

Players choose whether to appear in the ranking from their profile. The visible list is the
**top 50** by season XP, ordered by `XP descending → the moment they reached that XP ascending →
user id ascending` — a fully deterministic tie-break, so two players with the same XP are ranked
by who got there first.

## Staff / student split

Rankings are split into two groups: **students** and **staff** (anyone with course-editing
capability in at least one course, or the site-admin flag). Whether a viewer sees one or both
groups depends on their own role and the site's participant setting:

- A regular student or teacher sees only their own group's ranking.
- An admin, or any staff member when the site is configured to allow **both** groups to
  participate, sees **both** the Students and Staff tabs.

## Your own position, even outside the top 50

A player who opted into the ranking but isn't in the visible top 50 still sees their **own exact
position** — computed with a single indexed count query against the same ordering rule used for
the visible list, not by scanning the whole ranking.

## Who stays on the list

Opting out of the gamification (Preferences → Gamification) takes a player off both rankings at once,
and turning it back on starts hidden: they choose again whether to show up. A player whose role lost
the `local/playergames:viewhub` capability, or whom a change of the participant setting no longer
allows, is also left out of the list, within a minute (the lists are cached for 60 seconds).

## How the staff group is kept up to date

Working out who counts as staff takes several queries for every teacher of the site, so a scheduled
task, **Refresh the set of staff users** (`refresh_staff_ids`), does it **every 10 minutes** and the
hub only reads the result. This has two consequences for administrators:

- A change of someone's roles can take up to about 10 minutes to move them between the Students and
  Staff groups. What a person is allowed to do is never decided by this copy: pages and services
  check the role live.
- The site needs its **cron running**. If it is not, the plugin keeps working: after one hour with no
  refresh the hub works the set out again by itself, at the cost of a slower page for whoever opens
  it first.

## Scope

This page describes the **season** ranking (tied to season XP and reset when a season closes).
[Course XP](#learning-xp) has its own separate, single (not staff/student-split) ranking with
its own opt-in — see that section for details.
