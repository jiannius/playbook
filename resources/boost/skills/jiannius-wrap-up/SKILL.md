---
name: jiannius-wrap-up
description: "Wrap up a Claude Code session in a Jiannius repo before closing it. Say 'wrap up' or 'close the session'. Clears leftovers, settles git and worktrees, writes the handoff."
---

# Wrapping up a session

While this skill is active you are **closing out a working session** in a Jiannius repo: leave the
repo the way you would want to find it, and tell the person it is safe to exit.

> This skill arrives from `jiannius/playbook` and is rewritten by `boost:update`. Edit it in that
> package, never in `.claude/skills/` — a local edit is overwritten on the next update.

Claude Code already handles some of this on exit — background tasks stop, a worktree entered with
`EnterWorktree` prompts keep-or-remove, memory is saved as you go. This skill covers what it leaves
behind. You cannot end the session yourself; the last step is telling the person to type `/exit`.

Branch, commit and push rules belong to `jiannius-dev`, and the universal rules — production,
secrets and customer data, ask when unsure — come from the guidelines block of this repo's agent
file. This skill does not restate them.

## The one rule

**Files traceable to this session are removed without asking; everything else is asked about, once.**

The conversation is the inventory: the screenshots, scratch scripts and exports you wrote. Those go.
Everything else — a file you cannot trace to this session, every git action (commit, push, branch or
worktree removal), anything outside the repo — is listed and put in **one question covering the
whole batch**. Do not ask per item.

If the session was resumed or compacted, treat anything you cannot see in the conversation as
untraced.

Never discard work. Uncommitted changes, unpushed commits and unmerged branches are reported, not
removed.

## 1. Hand the checkout back

If the QA skill ran, its own final step owns returning the checkout to its starting branch,
including the migration rollback. If the main checkout is still on a PR branch, do that step first —
the rollback needs the PR's migration files, which are gone once you leave the branch.

## 2. Take stock

```bash
git fetch --prune
git status --porcelain --ignored
git worktree list
git branch -vv
```

Run these in the main checkout as well as any worktree — the leftovers can be in either. `--ignored`
shows gitignored leftovers such as `.playwright-mcp/` and `storage/`. Stop any server you started
outside a background task, which outlives the session, and close the Playwright browser if you
opened one.

## 3. Put back what you switched

- **`.env` edits** — a changed `DB_DATABASE`, a toggled `APP_DEBUG`, a test key. Restore the value
  that was there before; if you are not sure what it was, put it in the question. Never print `.env`
  values in the summary.
- **Herd** — sites linked, secured or isolated during the session, if they were not meant to stay.

## 4. Remove what this session made

- **Screenshots** — `.playwright-mcp/`, PNGs written to the repo root or `storage/`, taken to look
  at a page.
- **Temporary scripts** — one-off `*.php` / `*.sh` / `*.js` probes and tinker scratch files.
- **Temporary data** — exported CSVs, query dumps, downloaded files.

Judge "made this session" by the conversation and by mtime (`ls -lt`) — a file older than the
session is untraced. Tracked files are never removed here.

**Exception — QA screenshots.** `jiannius-qa-tester` has the tester drag screenshots into issues by
hand, so you cannot know an upload happened. A screenshot tied to a filed or pending bug is listed
and kept unless the person says they are done with it.

A `dd()`, `dump()`, `ray()` or debug route left in code is a change, not a clean-up. Raise it in
step 5; don't silently edit committed work.

## 5. Settle git and the rest

Report the state, then ask **once** for everything that needs a decision:

- **Uncommitted changes** — do they belong in a commit? Commit only on the person's say-so,
  following the repo's commit conventions.
- **Unpushed commits** — read the `git branch -vv` output: `ahead` means unpushed, no upstream means
  the branch exists only on this machine, `[gone]` means the remote branch was deleted. Push on the
  person's say-so.
- **Finished branches** — Jiannius squash-merges PRs, so `git branch --merged` and `-d` never
  recognise them. A branch is done when `git branch -vv` shows `[gone]` or
  `gh pr view <branch> --json state` reports `MERGED`. List those and offer `git branch -D`.
- **Worktrees made with `git worktree add`** — Claude Code will not prompt for these, and an
  `EnterWorktree` worktree still listed gets the same treatment. Clean and merged or pushed → offer
  to remove it. Run `git worktree remove <path>` from the main checkout (`git -C`), not from inside
  the worktree, and glance at its ignored `.env` / `DB_DATABASE` first: removal deletes ignored
  files. Remove a worktree before deleting the branch it holds.
- **A local database this session created** (for a second worktree, say) — drop it only after
  confirming `DB_HOST` is local and the name is one this session created, and only once its
  worktree is gone.
- **Untraced files** from steps 2 and 4, listed so the person can decide.

Never `git worktree remove --force`, never `-D` a branch whose commits exist nowhere else, and never
touch worktrees or branches another session made.

## 6. Handoff

Finish with a short summary:

- **Shipped** — commits, PRs and issues, each linked and described in a few words.
- **Still open** — what is unfinished, and the next step.
- **Left in place** — anything you deliberately did not clean up, and why.

Then: **"Safe to `/exit`."** If something is still waiting on the person — a push, a decision, an
unmerged branch — say that instead.

## Guardrails (role-specific)

- Session files are removed without asking; everything else goes into the one question in step 5.
- Nothing destructive outside the repo except what this session created — a local database, a Herd
  link. A database is dropped only after confirming `DB_HOST` is local and the name is one this
  session created, as part of that question.
- The universal rules — production, secrets and customer data, ask when unsure — are in the
  guidelines block of this repo's agent file and apply in addition to the above.
