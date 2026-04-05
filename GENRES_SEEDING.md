# Genre Seeding

For this project, `genres` should be treated as a local application table, not as a live dependency on Last.fm.

## Why

- onboarding should work even if Last.fm is unavailable
- matching logic should use stable local genre IDs
- external tags are messy and should enrich your data, not replace your core schema

## Recommended approach

1. Keep a fixed local `genres` table in MySQL
2. Seed it once with common genres
3. Use Last.fm tags later only for enrichment or mapping

## Seed command

Run:

```bash
php scripts/seed_genres.php
```

## Suggested data model split

- local DB genres:
  - Pop
  - Rock
  - Hip-Hop
  - Indie
  - Electronic
  - Jazz
  - R&B
  - etc.

- Last.fm tags:
  - used for similarity, vibe, and explanations
  - can later be mapped onto local genres if needed

Example:

- Last.fm tag `indie rock` maps to local genre `Indie Rock`
- Last.fm tag `garage rock revival` could map to local genre `Garage Rock`
- Last.fm tag `seen live` should not become a core genre
