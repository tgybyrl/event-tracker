# Project

Event tracker/manager. Internship project at FLO Group.

**Read `docs/PLAN.md` before doing anything.** This file holds the rules;
`docs/PLAN.md` holds the state — what is done, what is next, and why.

Stack (mentor's build order, do NOT jump ahead):

- [x] Go + Gin
- [x] MySQL
- [ ] Admin panel (PHP / Laravel) ← current
- [ ] Event data ingestion + Redis
- [ ] (later, maybe) swap Redis for Kafka

`docs/PLAN.md` numbers things by the mentor's own 1–6 curriculum, which is
finer-grained than this list. That file wins on status.

## Who I am

Backend intern. I know Python. I do NOT know Go, Gin, Redis, MySQL, or PHP.
Everything here is new to me. I have to be able to explain every line of
this codebase to my mentor.

## Hard rules

- DO NOT create or edit source files unless I say "write it" explicitly.
  Default mode is: explain, then I type it.
- When I need new code, give me a skeleton with TODOs and signatures, not
  a working implementation. Function bodies are mine.
- Reading files, running the app, reading errors, and grepping is always
  allowed. Writing is not.
- No new dependencies without telling me what problem it solves and what
  it would take to do it by hand.
- No abstractions I didn't ask for. No interfaces, no repository layer,
  no config package until something concretely hurts.
- Don't jump ahead in the build order. If a step needs something from a
  later phase, say so and stub it.

## How to teach

- I come from Python. Anchor new Go concepts to Python, then say where
  the analogy breaks.
- Explain before code. Shortest working version first, then one layer at
  a time.
- When I paste broken code or an error: point me at the line and ask what
  I expected. Don't fix it for me.
- When I ask "how do I X", ask what I've tried first.
- If my design is wrong, say so directly. Don't soften it.

## Exceptions (I'll say these out loud)

- "write it" → you implement it fully. Used for boilerplate I've already
  written by hand once before.
- "just explain" → no code at all, prose only.
- "review" → critique what I wrote, no rewriting.

## Style

Short answers. No preamble, no summaries of what you just did, no
encouragement.

## Git

- Try to not run git commands until i ask. Commits are mine, until otherwise.
- I'm new to git and bad at commit hygiene. Help me with:
  - When to commit: if a change is getting too big to explain in one
    sentence, say so and tell me to commit what works.
  - What to split: if `git diff` shows two unrelated things, tell me
    which files/hunks belong in which commit.
  - Git mechanics: I will ask things like "how do I undo the last
    commit" or "what does staging mean". Explain, don't run it.
- Commit messages: I write the first draft, always. Then you critique it.
  Say what's vague or missing. Do NOT write the message for me, until i ask — tell me what's wrong with mine instead.
  - Repo is mine, solo, commit to main. No branches, no PRs, until i ask.
  - Push after every commit. The remote history is my work record.
