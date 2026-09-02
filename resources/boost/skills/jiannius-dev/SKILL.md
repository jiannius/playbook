---
name: jiannius-dev
description: "Work on an existing Jiannius Laravel project: fix a bug, add an enhancement, or review a PR. Say 'fix this bug', 'start issue #123', or 'review this PR'. Branch off dev, Conventional Commits."
---

# Working on an existing Jiannius project

While this skill is active you assist a **developer changing an existing Jiannius Laravel project** (Laravel + Livewire + the Atom component library) — **bug fixes, enhancements, or code review**. This is *not* for greenfield / new projects (that's a separate workflow).

> This skill arrives from `jiannius/playbook` and is rewritten by `boost:update`. Edit it in that
> package, never in `.claude/skills/` — a local edit is overwritten on the next update.

## Where the rules live

Three sources. **The more specific one wins when they genuinely conflict.**

| Source | Carries | Loaded |
|---|---|---|
| The playbook guidelines, inside this repo's `CLAUDE.md` / `AGENTS.md` | Production is off-limits, secrets and customer data, CI cost, ask when unsure | Always |
| This skill | The cross-repo *process* — branch, commit, PR, review | On demand |
| The repo's own constitution, below the managed block | Structure, naming, Atom usage, test rules for **this** codebase | Always |

The repo wins on specifics. This skill deliberately does not restate the universal rules — they are
already in context from the guidelines block.

## Where you fit in the flow

- Work from a **GitHub issue**. Branch off **`dev`**.
- **Conventional Commits** (`feat:` / `fix:` / `refactor:` / `docs:` / `test:` / `chore:`) — the changelog depends on them.
- Open a **`feature → dev` PR**, request the **QA tester** as reviewer.
- Release is a maintainer-controlled **`dev → main`** merge — not yours to do.

## Making a change (bug fix / enhancement)

1. Start from the issue. Branch off `dev` — `fix/<short-desc>` for a bug, `feat/<short-desc>` for
   an enhancement:
   ```bash
   git checkout dev && git pull && git checkout -b fix/<short-desc>
   ```
2. Implement following the **repo's conventions** and existing **Atom components** (don't reinvent UI the component library already provides).
3. Run **Pint** before finalizing PHP:
   ```bash
   ./vendor/bin/pint
   ```
   On a repo with no `pint.json`, run it on **your changed files only** — a wholesale run reformats
   unrelated code and buries the diff.
4. **Add or update the test** for the change and run it. Scope — minimum set while you work, whole
   suite once before you push — is the guidelines block's rule, and this skill does not restate it.
5. Run **`npm run build`** when you add Tailwind classes or JS (hashed assets won't render otherwise).
6. Commit using **Conventional Commits**.
7. Open a **`feature → dev` PR**, **link the issue**, and request the **QA tester** as reviewer.

If you changed anything that affects how the guidelines are delivered — a new package, a Boost
config change, an edit to `jiannius/playbook` itself — run `php artisan playbook:check` before
pushing. CI runs it and fails the build when the agent files are stale; the fix is
`php artisan boost:update` and commit the result.

## Working alongside another branch

Mechanical checks are CI's job — a fresh-database `migrate:fresh --seed` and the base-branch guard
both run there, so you do not need to remember them. What CI cannot decide for you:

- **Run two branches in parallel** when the features touch different domains. **Stagger** them when
  they share hot files or, especially, the same tables — `migrate:fresh` replays in filename
  timestamp order, which need not match merge order, so a branch can pass alone and still break a
  rebuild once someone else's migration sits ahead of it.
- **A second worktree needs its own `DB_DATABASE`**, or one branch's migrations move the other's
  schema underneath it. It also starts with no `vendor/`, no `node_modules/` and no `.env`.

## Reviewing a PR / code (review mode)

When asked to review, check the diff against Jiannius conventions and report findings (complements the built-in `/code-review` — add Jiannius-specific notes):

- Conventional Commit messages; branch targets `dev`, not `main`.
- Follows the repo's own constitution (structure, naming, test rules).
- Uses **Atom components** rather than bespoke UI where applicable.
- **Pint-clean**; tests present and green; `npm run build` covered if Tailwind/JS changed.
- Agent files current — `playbook:check` passes.

## Guardrails (role-specific)

- **Never push to `main`.** Ship via PR → `dev`; the `dev → main` release is maintainer-only.
- Follow the repo's own constitution; don't invent conventions that contradict it.
- The universal rules — production, secrets and customer data, ask when unsure — are in the
  guidelines block of this repo's agent file and apply in addition to the above.
