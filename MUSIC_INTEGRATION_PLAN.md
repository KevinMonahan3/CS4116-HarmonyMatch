# Music Integration Plan

This project should use:

- MusicBrainz for search and canonical music metadata
- Last.fm for enrichment such as similar artists, top tags, and top tracks
- MySQL as the application's source of truth

## Why both

MusicBrainz is best for turning messy user input into consistent artist and track records.
Last.fm is best for discovery signals and similarity data that can improve compatibility scoring.

## Recommended architecture

### 1. Frontend pages

- `onboarding.php`
  - Search artists and tracks through our backend, not directly from the browser
  - Let users pick suggested artists and songs
- `profile-own.php`
  - Reuse the same music search endpoints for editing preferences
- `dashboard.php`
  - Use only our database for match results
  - Show cached enrichment such as related artists or shared tags

### 2. Backend layers

- `api/music.php`
  - HTTP endpoints used by the frontend
- `controllers/MusicController.php`
  - Validate requests and shape JSON responses
- `services/MusicBrainzService.php`
  - Search artists and recordings
- `services/LastFmService.php`
  - Fetch similar artists, top tags, and top tracks

### 3. Storage strategy

Short term:

- Cache third-party responses in simple file cache to reduce repeat calls

Long term:

- Add database-backed cache tables such as:
  - `external_artist_cache`
  - `external_track_cache`
  - `artist_enrichment_cache`

### 4. Matching strategy

Use your own DB for the final match score:

- shared genres
- shared artists
- shared songs
- optional bonus if users like artists that Last.fm says are similar
- optional bonus if their artists share top tags

## Suggested implementation order

1. Seed the `genres` table
2. Add backend services and `/api/music.php`
3. Add artist search to onboarding
4. Add track search to onboarding
5. Save chosen artists and songs into existing local tables
6. Add Last.fm enrichment cache
7. Use enrichment in compatibility explanations on the dashboard

## Important constraints

- MusicBrainz requires a meaningful `User-Agent`
- MusicBrainz rate limit guidance is one request per second per application
- Last.fm requires an API key
- Do not call these services directly from frontend JS
- Cache aggressively because the VM only has 1 GB RAM
