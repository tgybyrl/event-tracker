# Project

Event tracker. Internship project at FLO Group, ending in a presentation to
my mentor — I have to be able to explain every part of it.

**Read `docs/PLAN.md` before doing anything.** This file holds the rules;
`docs/PLAN.md` holds the state — what exists, what is next, and why.
`docs/HISTORY.md` is the build log; read it when you need to know why
something is the way it is.

Build order (mentor's, do not jump ahead without asking):

- [x] Go + Gin — event API
- [x] MySQL
- [x] Admin panel (PHP / Laravel)
- [x] Demo market that sends real events (added 2026-09-28)
- [ ] Event ingestion + Redis Streams ← next (measure with `cmd/loadgen` first)
- [ ] (later, maybe) swap Redis for Kafka

## Who I am

Backend intern. I came in knowing Python and none of Go, Gin, Redis, MySQL,
PHP or Laravel.

## How we work (since 2026-09-23)

- **You write the code.** I say what I want; you plan it, build it and
  verify it by running it (curl, SQL, headless browser) before calling it
  done.
- **Explain every step after it lands**: what the code does, why it is built
  that way, with `file:line` pointers. Plain language, as to someone seeing
  it for the first time. **No Python or Django analogies.** I will also ask
  for detailed walkthroughs of whole areas before the presentation.
- **Tests are mine.** Do not add Go or Laravel tests unless I ask. Say where
  a test would be worth writing.
- Bigger work gets a plan first (plan mode), and I approve it.
- Follow `docs/PLAN.md`'s roadmap. Improvising beyond it is fine — ask me
  first.
- Keep `docs/PLAN.md` current as part of the work, so a fresh session after
  `/clear` can pick up where we stopped.

## Rules that still hold

- No new dependencies without telling me what problem it solves and what
  it would take to do it by hand.
- No abstractions nothing asked for. No interfaces, no repository layer,
  no config package until something concretely hurts.
- Data model and DB schema changes go to the mentor first (see "Open
  questions for mentor" in PLAN.md). They are expensive to undo.
- If my design is wrong, say so directly. Don't soften it.
- When I paste broken code or an error: point me at the line and ask what
  I expected. Don't fix it silently.

## Words I use

- "write it" → implement it fully.
- "just explain" → no code, prose only.
- "review" → critique what I wrote, no rewriting.

## Style

Chat replies in Turkish, technical terms in English. `docs/` and code
comments in English. Short answers unless I ask for depth. No preamble, no
encouragement.

## Git

- Work on a **branch** per piece of work, never straight on `main`.
- **One commit per step**, pushed right away — if I dislike a step, it can
  be undone on its own. The remote history is my work record.
- Commit messages: `type(scope): summary`, then a body that says why.
- **No `Co-Authored-By` or `Claude-Session` trailers.**
- **Merge into `main` only when I say so** (fast-forward), then push.
- I still ask git mechanics questions ("how do I undo the last commit",
  "what is staging"): explain, don't just run it.
