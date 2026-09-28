# Laravel admin panel

Read `../docs/PLAN.md` first — it holds the current state and the roadmap.
The root `CLAUDE.md` applies here too.

- Served under **`/admin`** (`Route::prefix('admin')` in `routes/web.php`),
  reached through the Caddy proxy at `http://localhost/admin`. Write links
  with `route(...)`, never as hand-written paths.
- This panel is the **read** side. Go writes events, and the panel reads
  them **only through the Go API** (`app/Services/EventsApi.php` →
  `GET /api/v1/events`, `/events/facets`, `/events/stats`). There is no
  Eloquent model for `events`; do not add one.
- **Do NOT create a migration for the `events` table.** Go owns it and
  `db/schema.sql` is the single source of truth.
- The panel's own tables (users, sessions, cache, jobs) live in `panel_db`,
  reached as the `panel` MySQL user, which has **no rights on `events_db`**.
  `php artisan migrate` runs against `panel_db` only. Credentials come from
  `admin/.env`, which is gitignored.
