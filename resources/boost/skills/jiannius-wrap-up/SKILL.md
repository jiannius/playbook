---
name: jiannius-wrap-up
description: "Wrap up a Claude Code session in a Jiannius repo before closing it. Say 'wrap up', 'close the session' or 'I'm done'. Clears leftovers, settles git and worktrees, writes the handoff."
---

# Wrapping up a session

While this skill is active you are **closing out a working session** in a Jiannius repo: leave the
machine and the repo the way you would want to find them, record what matters, and tell the person
it is safe to exit.

> This skill arrives from `jiannius/playbook` and is rewritten by `boost:update`. Edit it in that
> package, never in `.claude/skills/` — a local edit is overwritten on the next update.

You cannot end the session yourself. The last step is telling the person to type `/exit`.

## The one rule

**Clean up only what this session made.** The conversation is the inventory: the files you wrote,
the worktrees and branches you created, the processes you started, the settings you switched. A
file you cannot trace back to this session is not yours to delete, however useless it looks — list
it and ask.

Never discard work. Uncommitted changes, unpushed commits and unmerged branches are reported, not
removed.

## 1. Take stock

Read back through the session and list what it left behind, then check the machine agrees:

```bash
git status --porcelain
git worktree list
git branch -vv
git stash list
```

Run these from the main checkout as well as any worktree — the leftovers can be in either.

## 2. Stop what you started

- Background shells and tasks you ran — `php artisan serve`, `npm run dev`, queue workers,
  `php artisan schedule:work`, file watchers. Stop them by the task you started, not by killing
  every `php` or `node` process on the machine.
- The Playwright browser, if you opened one — close it.
- Monitors, scheduled wakeups or cron jobs you created for this session only.

Do this first. A running dev server can hold files the next steps want to remove.

## 3. Put back what you switched

- **`.env` edits** — a changed `DB_DATABASE`, a toggled `APP_DEBUG`, a test key. Restore the value
  that was there before, and confirm with the person if you are not sure what it was.
- **The main checkout's branch.** If the session checked out a PR or feature branch there (QA does
  this), return to the branch it was on at the start — after the git step below has settled
  anything on it.
- **Herd** — sites linked, secured or isolated during the session, if they were not meant to stay.
- **A database you created** for a second worktree — drop it only once its worktree is gone, and
  only if it is a local throwaway.

## 4. Remove the leftovers

Things that served the session and nothing after it:

- **Screenshots** — `.playwright-mcp/`, PNGs written to the repo root or `storage/`, anything taken
  to look at a page rather than to attach to an issue or PR. A screenshot already uploaded to a
  bug report has done its job; one that is about to be uploaded has not.
- **Temporary scripts** — one-off `*.php` / `*.sh` / `*.js` probes, tinker scratch files, debug
  routes or `dd()` / `ray()` / `dump()` calls left in code.
- **Temporary data** — exported CSVs, query dumps, downloaded files.
- **The session scratchpad directory**, if one was used.

Check each against `git status`. Untracked and made this session → remove it. Tracked, or untracked
but older than the session → leave it and mention it.

A leftover that should not exist at all — a debug line in committed code — is a change, not a
clean-up. Raise it in the git step, don't silently edit committed work.

## 5. Settle git

Report the state, then ask **once** for everything that needs a decision:

- **Uncommitted changes** — whose are they, and do they belong in a commit? Commit them only on the
  person's say-so, following the repo's commit conventions.
- **Unpushed commits** — `git log @{u}..` on each branch the session touched. Push on the person's
  say-so. Never push to `main`.
- **A branch with no upstream** — say so; it exists only on this machine.
- **Stash entries the session made** — apply or drop them by their SHA, never with a bare
  `git stash pop`; the stash stack is shared with every worktree and every other session.

## 6. Worktrees and branches

For each worktree **this session created**:

- **Merged or fully pushed, and clean** → `git worktree remove <path>`, then delete its branch
  (`git branch -d <branch>`). If the session entered it with `EnterWorktree`, leave it with
  `ExitWorktree` and `action: "remove"` instead.
- **Uncommitted or unpushed work** → keep it, and list it in the handoff with what is left to do.

Then local branches the session created that are now merged — `git branch --merged` — can go with
`git branch -d`. Never `-D` a branch whose commits are not reachable from somewhere else, and never
`git worktree remove --force`. Worktrees and branches from other sessions are not yours, even if
they look stale.

## 7. Memory

Save what a future session would need and could not get from the repo — a non-obvious cause found
the hard way, a decision the person made and why, a correction to how you worked. Update an existing
memory rather than adding a second one. Don't save what the code, the git history, an issue or the
repo's constitution already records.

## 8. Handoff

Finish with a short summary, in this order:

- **Shipped** — commits, PRs and issues, each linked and described in a few words, never a bare
  `#123`.
- **Still open** — what is unfinished, and the next step.
- **Left in place** — anything you deliberately did not clean up, and why.
- **Cleaned up** — one line: processes stopped, files removed, worktrees removed.

Then: **"Safe to `/exit`."** If something is still waiting on the person — a push, a decision, an
unmerged branch — say that instead, and what it is.

## Guardrails (role-specific)

- Never delete, commit, push or discard without the person's go-ahead where this skill says to ask.
  One question covering the whole batch beats a question per file.
- Nothing destructive outside the repo and the session's own scratch space.
- The universal rules — production, secrets and customer data, ask when unsure — are in the
  guidelines block of this repo's agent file and apply in addition to the above. A wrap-up never
  touches production or a shared environment.
