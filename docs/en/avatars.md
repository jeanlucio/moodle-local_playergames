# 🦊 Avatars

PlayerGames has its own emoji avatar collection, unlocked permanently by the highest level a
player has ever reached — no shop, no file uploads, just a level-tier reward.

## Catalog and tiers

The default catalog has **17 emoji avatars** split across **4 tiers** (5 in tier 1, 4 each in
tiers 2–4): 🤖 👾 🦊 🐱 🐶 (tier 1), 🕵️ 🤺 🧚 🧜 (tier 2), 🧝 🧙 🦸 🥷 (tier 3), and 🧛 🦹 🐉 🦁
(tier 4).

## Unlock rule

Unlocks are based on the **highest level the player has ever reached, for life** — not their
current level, and not scoped to the current season. Every time a player earns enough XP to
level up, PlayerGames records the new level only if it exceeds the best level stored for that
player; closing a season, which resets season XP back to zero, never takes an already-unlocked
avatar away.

## Tier thresholds

By default the tiers are spread evenly across the level ladder, so every avatar stays reachable
however many levels the ladder has: tier 1 opens at level 1 and each later tier a further quarter
of the ladder up.

| Tier | 5 levels (default ladder) | 20 levels |
|---|---|---|
| 1 | 1 | 1 |
| 2 | 2 | 6 |
| 3 | 3 | 11 |
| 4 | 4 | 16 |

Admins can set a fixed minimum level for any tier (0 keeps the automatic spread)
(*Site administration → Plugins → Local plugins → PlayerGames → Rules → Avatars*).

## Equip and display

A player can equip any avatar they've unlocked (or unequip back to their default Moodle profile
picture) from a tier-grouped picker — locked avatars show grayed out alongside their required
level. The equipped avatar replaces the default profile picture in the Player Hub's profile card
and in the [PlayerGames block](#ecosystem)'s widget, and stays in sync between the two since both
read and write through the same avatar manager.
