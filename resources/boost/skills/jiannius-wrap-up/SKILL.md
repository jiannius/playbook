---
name: jiannius-wrap-up
description: "Wrap up a Claude Code session in a Jiannius repo before closing it. Say 'wrap up', 'close the session' or 'I'm done'. Clears leftovers, settles git and worktrees, writes the handoff."
---

# Wrapping up a session

While this skill is active you are **closing out a working session** in a Jiannius repo: leave the
repo the way you would want to find it, and tell the person it is safe to exit.

> This skill arrives from `jiannius/playbook` and is rewritten by `boost:update`. Edit it in that
> package, never in `.claude/skills/` — a local edit is overwritten on the next update.

Claude Code already handles some of this on exit — background tasks stop, a worktree entered with
`EnterWorktree` prompts keep-or-remove, memory is saved as you go. This skill covers what it leaves
behind. You cannot end the session yourself; the last step is telling the person to type `/exit`.

## The one rule

**Clean up only what this session made.** The conversation is the inventory: the files you wrote,
the branches and worktrees you created, the settings you switched. A file you cannot trace back to
this session is not yours to delete, however useless it looks — list it and ask.

Never discard work. Uncommitted changes, unpushed commits and unmerged branches are reported, not
removed.

## 1. Take stock

```bash
git status --porcelain
git worktree list
git branch -vv
```

Run these in the main checkout as well as any worktree — the leftovers can be in either. Stop any
server you started outside a background task, which outlives the session, and close the
Playwright browser if you opened one.

## 2. Put back what you switched

- **`.env` edits** — a changed `DB_DATABASE`, a toggled `APP_DEBUG`, a test key. Restore the value
  that was there before, and confirm with the person if you are not sure what it was.
- **The main checkout's branch.** If the session checked out a PR or feature branch there (QA does
  this), return to the branch it was on at the start — after step 4 has settled anything on it.
- **Herd** — sites linked, secured or isolated during the session, if they were not meant to stay.
- **A database you created** for a second worktree — drop it only once its worktree is gone, and
  only if it is a local throwaway.

## 3. Remove the leftovers

- **Screenshots** — `.playwright-mcp/`, PNGs written to the repo root or `storage/`, anything taken
  to look at a page rather than to attach to an issue or PR. One already uploaded to a bug report
  has done its job; one about to be uploaded has not.
- **Temporary scripts** — one-off `*.php` / `*.sh` / `*.js` probes and tinker scratch files.
- **Temporary data** — exported CSVs, query dumps, downloaded files.

Check each against `git status`. Untracked and made this session → remove it. Tracked, or untracked
but older than the session → leave it and mention it.

A `dd()`, `dump()`, `ray()` or debug route left in code is a change, not a clean-up. Raise it in
step 4; don't silently edit committed work.

## 4. Settle git

Report the state, then ask **once** for everything that needs a decision:

- **Uncommitted changes** — do they belong in a commit? Commit only on the person's say-so,
  following the repo's commit conventions.
- **Unpushed commits** — `git log @{u}..` on each branch the session touched. Push on the person's
  say-so. Never push to `main`.
- **A branch with no upstream** — say so; it exists only on this machine.
- **Worktrees made with `git worktree add`** — Claude Code will not prompt for these. Clean and
  merged or pushed → `git worktree remove <path>`; otherwise keep and list it.
- **Branches the session created that are now merged** (`git branch --merged`) → `git branch -d`.

Never `git worktree remove --force`, never `-D` a branch whose commits exist nowhere else, and never
touch worktrees or branches another session made.

## 5. Handoff

Finish with a short summary:

- **Shipped** — commits, PRs and issues, each linked and described in a few words, never a bare
  `#123`.
- **Still open** — what is unfinished, and the next step.
- **Left in place** — anything you deliberately did not clean up, and why.

Then: **"Safe to `/exit`."** If something is still waiting on the person — a push, a decision, an
unmerged branch — say that instead.

## Guardrails (role-specific)

- Ask before deleting, committing or pushing. One question covering the whole batch beats a
  question per file.
- Nothing destructive outside the repo.
- The universal rules — production, secrets and customer data, ask when unsure — are in the
  guidelines block of this repo's agent file and apply in addition to the above.
