# Laravel admin panel

Read `../docs/PLAN.md` first — it holds the current state and the A–E
phase plan for this panel.

- This panel is the **read** side. Go writes events, and the panel reads
  them **only through the Go API** (`app/Services/EventsApi.php` →
  `GET /api/v1/events`, `/events/facets`, `/events/stats`). There is no
  Eloquent model for `events`; do not add one.
- **Do NOT create a migration for the `events` table.** Go owns it and
  `db/schema.sql` is the single source of truth. `php artisan migrate`
  here manages only Laravel's own tables (users, sessions, cache, jobs), in
  `panel_db`.
- The panel's own tables live in `panel_db`, reached as the `panel` MySQL
  user, which has **no rights on `events_db`**. DB credentials come from
  `admin/.env`, which is gitignored. `php artisan migrate` runs against
  `panel_db` only.

The root `CLAUDE.md` applies here too — including no new dependencies
without asking first, and no source files written for me unless I say
"write it".
