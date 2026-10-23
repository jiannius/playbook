---
name: jiannius-qa-tester
description: "QA-test a Jiannius Laravel PR. Say 'pull the pending PR and do the QA test' or 'QA test PR #123'. Sets the PR up on Herd, browser-tests with Playwright + screenshots, files a bug or approves."
---

# QA testing a Jiannius PR

While this skill is active you are assisting a **QA tester** on a Jiannius Laravel project (Laravel + Livewire + the Atom component library). The job is **manual browser testing** of a pull request on the tester's **local machine (Laravel Herd)** — confirming the UI/UX looks right and real operations behave correctly.

**Stay in the QA lane.** You are NOT here to do formal code review, write/run the automated test suite, or fix bugs. When a test surfaces a bug you may do a *light, read-only* look at the code to suggest a likely cause (Step 4) — never change it.

> This skill arrives from `jiannius/playbook` and is rewritten by `boost:update`. Edit it in that
> package, never in `.claude/skills/` — a local edit is overwritten on the next update.

Anything project-specific — the app's `.test` URL, how to seed data, special setup — lives in **the repo you're testing** (`README.md` and the repo's own constitution). Read those for specifics; this skill is the shared process. The universal rules (production is off-limits, secrets and customer data, ask when unsure) are already in context from the guidelines block of the repo's agent file; the QA-specific guardrails at the bottom are *in addition* to those.

## Where this fits in the workflow

- A developer opens a **`feature → dev` pull request** (one per change/issue) and requests the tester as reviewer.
- You help the tester **set the PR up locally, exercise it in a real browser, and report what you find.**
- **Passes** → the tester submits an **Approve** review ("Testing done") — notifies the developer.
- **Broken** → help **open a bug issue**, and the tester clicks **Request changes**.
- Release (`dev → main`) is the maintainer's call — not your concern.

## 1. Set up the PR for testing

Ask the tester for the PR number (or branch) if not given. If they said "the pending PR" and it's ambiguous, list open PRs and confirm which one:

```bash
gh pr list --state open
```

Before switching branches, check the checkout is clean and note where it is:

```bash
git status
git branch --show-current
```

- **Uncommitted changes → stop and ask the tester.** `git checkout` carries them onto the PR branch, so
  you would be testing the PR plus their edits. Don't stash or discard them yourself.
- **Remember the starting branch** — step 6 returns to it.
- **Asked only for "before" screenshots** (a UI review asks for these)? Do section 7's *Before
  shots* and nothing else: skip the rest of this section and sections 2 to 6. It has its own way back.

Then, from the repo being tested:

```bash
gh pr checkout <PR-NUMBER>
composer install
npm install
npm run build
php artisan storage:link
php artisan migrate
```

| Step | When to run it |
|---|---|
| `gh pr checkout <PR-NUMBER>` | Always. Equivalent: `git fetch origin <branch>` then `git checkout <branch>` |
| `composer install` | PHP dependencies changed |
| `npm install` | JS dependencies changed |
| `npm run build` | **Always** — new Tailwind classes and JS will not render without it |
| `php artisan storage:link` | Once per machine |
| `php artisan migrate` | Only if the PR adds migrations. **Local database only, never production** |

- The app is served by **Herd** at `https://<project>.test` (always on). **Never run `php artisan serve`.** Get the exact URL from the repo's README.
- **Test in the main checkout, not a worktree.** Herd serves the main checkout's folder at that URL, so
  a worktree is not what the browser shows. A worktree also starts with no `vendor/`, `node_modules/`
  or `.env`, and shares the local database unless it gets its own `DB_DATABASE`. Use one only when
  the tester must keep their current checkout untouched — then the tester links it in Herd to its own
  URL themselves, and it gets its own `DB_DATABASE`.
- You need a **test organization / sample data** to exercise features. Use local dev data or seed it — **never production data.**
- If a page looks unstyled or a change isn't visible, it's almost always a missing `npm run build` or a needed hard-refresh (assets are hashed).

## 2. Know what to test

- Read the **PR description + every linked issue** — that is your test scope. Skim `CHANGELOG.md` (top "Unreleased" section) for context.
- If a commit body has a `Test:` note, follow it — it's the developer's explicit "verify this."

## 3. Test it — in the browser, like a real user

Focus on **UI/UX and actual operation**, not code. Drive the browser with **Playwright** and **capture screenshots**; the final UX judgment is the human's, so surface what you see and let them decide.

- **Flow works end-to-end?** Happy path, then realistic variations/edge cases (empty inputs, large values, cancel midway, back button, re-submit).
- **Looks right?** Spacing/alignment, responsive down to mobile, light **and** dark mode, numbers/labels/currency correct, buttons say what they do.
- **Actions do what they claim?** "Export" produces the export; "Save" persists and survives a reload.
- **Error/empty states sensible?** Clear messages, no raw errors, no broken layout.
- **UI changed?** In Claude Code, take section 7's "after" screenshots now.

## 4. Report a bug

Open a GitHub issue with the repo's **"QA bug report"** template:

```bash
gh issue create --template qa-bug.yml
```

Include: the PR/branch, exact **steps to reproduce**, **expected vs actual**. Reference the PR so the developer connects it. The template auto-applies the `bug` label. Then the tester clicks **Request changes** on the PR.

**Attach a screenshot whenever you can:**

- If you drove the browser with Playwright, **save the screenshot** (PNG on disk) and tell the tester the file path.
- **`gh` cannot upload images into issue markdown.** So either the tester **drags the PNG into the issue in the GitHub web UI** (GitHub uploads + embeds it), or you reference an **already-hosted image URL**. Always capture the screenshot so there's evidence to attach.

### Light code analysis (optional, read-only)

Once a bug reproduces, you may do a **shallow read** of the code to pin the likely cause and add a **"Possible cause"** note to the issue — investigation, not a fix, not a full review:

- Trace from the symptom: the route / Livewire component / Blade view behind the broken screen, the method handling the failing action, an obvious bad condition, off-by-one, wrong variable, missing null-check, wrong column/relationship.
- Point precisely — reference **`file:line`** and quote the suspect snippet.
- Flag it as a **hypothesis** ("looks like `x` should be `>=` not `>`"). If a quick read doesn't reveal it, say so and stop.

## 5. Sign off (when it passes)

When the change works and looks right, the tester submits an **Approve** review on the PR (a short "Testing done" comment). That's the signal for the developer to proceed.

## 6. Hand the checkout back

Whether the PR passed or failed, leave the local app as you found it:

```bash
php artisan migrate:rollback --step=<N>
git checkout <starting-branch>
```

Run the rollback only if step 1 ran the PR's migrations; `<N>` is how many it added. Roll back **before** switching branches — the PR's migration files are gone once you leave it, and
the local schema would stay on the PR's version, so the next PR gets tested against the wrong database.
Then `npm run build` again if the tester goes back to using the app on that branch.

## 7. Screenshots for a UI review (Claude Code only)

When a PR changes what people see, take "after" screenshots during the normal QA run, so the main
session can offer a UI review (`jiannius-ui-review`). "Before" shots come later, and only if the
person says yes to that review. In any other tool, skip this section: take screenshots only as bug
evidence, and leave the `UI changes:` line out of the report.

### Does the PR change the UI?

Decide from the files the PR touches:

```bash
gh pr diff <PR-NUMBER> --name-only
```

| Counts as a UI change | Not on its own |
|---|---|
| `resources/views/`, any `*.blade.php` | Migrations, models, jobs, commands |
| Livewire components (`app/Livewire/`) and view components (`app/View/`) | Tests, CI, docs |
| `resources/css/`, `resources/js/`, the Tailwind config | API-only routes and controllers |
| Language files (`lang/`): labels and messages | Dependency bumps with no visible effect |

What you see while testing counts too: a screen that looks different is a UI change, whatever the
file list said.

### Test data only

- **Seeded or local test data only** — the repo's seeders, or records you create in this run.
  These screenshots go on a review page that can be shared, so never real customer data, and never
  a local copy of a production database. If you cannot tell whether the local data is real, stop
  and ask.
- Nothing secret on screen: no `.env` values, no tokens, no real people's email addresses or phone
  numbers.
- If the page shows the Laravel debug bar, hide it in the browser before each shot, with a style
  rule such as `document.querySelectorAll('.phpdebugbar').forEach((el) => { el.style.display = 'none'; })`,
  never by editing `.env`.

### Where they go

Save them under `storage/app/ui-review/pr-<N>/r<round>/` — round 1 unless the task names another.
Check that folder is gitignored before you write to it. `git check-ignore` prints the path when it is:

```bash
git check-ignore storage/app/ui-review/pr-<N>/r1/s01.png
```

If nothing prints, stop and report. Do not edit `.gitignore`.

### What to take

- Each affected screen at **desktop (1440 × 900)** and **phone (390 × 844)**, in light mode. Add
  dark mode only when the change touches colours or the theme.
- Number each screen once: its desktop and phone shots share the `NN`, as in `s01-desktop` and
  `s01-phone`.
- At most 8 screens a round. If more are affected, take the ones where the change shows best, and
  list the rest under `skipped` in the manifest.
- Use `scale: "css"`. Scroll the change into view, or take a full-page shot when it sits below the
  first screen.
- Name each file `s<NN>-<screen>-<desktop|phone>-<before|after>.png`, such as
  `s01-invoices-phone-after.png`, and pass the whole relative path as the file name.
- `npm run build` is the only npm command in this section: never `npm install`, and never leave a
  lock file changed. If `package-lock.json` or `composer.lock` changes, put it back with
  `git checkout -- <file>` before you take a shot.

**After shots** — in the normal QA run, once the PR is running (section 3). Write the manifest with
every `before` set to `null`, and set `new` on each entry (see *The manifest*): a new screen gets
no "before" at all.

**Before shots** — only when the task asks for them. Take them where the PR branched from its base
branch, the merge base, not the base's tip: a base that has moved on would show other people's
changes as the PR's. Use the same screens, at the same widths, as the round's manifest, with the same
data, but only the entries with `new: false`: never take a "before" for an entry with `new: true`. Check the checkout is clean and note the starting branch (section 1), then:

```bash
git fetch origin <base-branch>
git checkout --detach "$(git merge-base origin/<base-branch> <PR head sha>)"
npm run build
```

`<PR head sha>` is the manifest's `head_sha`.

| Step | When to run it |
|---|---|
| `git checkout --detach <merge base>` | Always: it moves no local branch |
| `composer install` | `composer.lock` differs from the starting branch's |
| `npm run build` | Always |

Do not run migrations here. If a base screen fails because the local database is behind, leave its
`before` as `null` and say why in `before_note`. Fill in each
`before`, `base` and `base_sha` in the manifest (`base_sha` is that merge base), then go back:

```bash
git checkout <starting-branch>
npm run build
```

**Round 2 and later** — the task names the round. Test the local PR branch as it is: it holds fix
commits that may not be on the remote yet, so never reset it to the remote. If `gh pr checkout`
refuses because the local branch and the PR have diverged, stop and report. Take "after" shots only,
of the screens the task lists; each one's "before" is the previous round's "after".

### The manifest

Write `manifest.json` in the round's folder:

```json
{
  "pr": 123,
  "title": "invoice PDF export",
  "round": 1,
  "head_sha": "<full SHA of the PR head you tested>",
  "base": "dev",
  "base_sha": "<full SHA of the merge base you photographed, or null>",
  "data": "DatabaseSeeder, test organisation Demo Co",
  "screens": [
    {
      "id": "s01-phone",
      "screen": "Invoices list",
      "path": "/invoices",
      "width": "phone",
      "after": "r1/s01-invoices-phone-after.png",
      "before": "r1/s01-invoices-phone-before.png",
      "before_note": null,
      "new": false,
      "changed": "Each row gets a PDF button.",
      "look_at": "Whether the button pushes the total onto a second line."
    }
  ],
  "skipped": [
    { "screen": "Invoice settings", "why": "Same button and layout as the list." }
  ]
}
```

- `title` is the short "what it is" the task gives, such as "invoice PDF export", not the PR's title.
- One entry in `screens` per screen and width; `id` is `s<NN>-<desktop|phone>`, with one `NN` per screen.
- Paths are relative to `storage/app/ui-review/pr-<N>/`.
- `before` is `null` until a *Before shots* task fills it, and stays `null` for a new screen or a
  base screen that would not load; `before_note` says why. Leave `before_note` `null` when `before`
  is set, except for a new screen (below).
- `new` is a boolean on every entry. It is `true` when the screen does not exist on the PR's base:
  the view, Livewire or component file for it is added by the PR (`gh pr diff <PR-NUMBER>
  --name-only` together with the file status, such as `git diff --name-status <merge-base>
  <head>` showing `A`), or its route does not exist on the base. When unsure, set `false`, so a
  "before" is attempted. A new entry keeps `before` as `null` and sets `before_note` to "New in
  this PR": the only case where `before_note` is set for a `null` `before` for this reason.
- `changed` and `look_at` are one plain sentence each: what the PR changed on this screen, and what
  to check.

### The report line

End the report with exactly one of these as its last line:

- `UI changes: yes — N after screenshots in <dir>, manifest <file> (M new screens)`
- `UI changes: yes — no screenshots (<reason>)`
- `UI changes: no`

A task that asked only for "before" shots ends instead with one of:

- `Before screenshots: N in <dir>, manifest <file>`
- `Before screenshots: none (<reason>)`

`N` counts the image files taken in this run, `<dir>` is the round's folder and `<file>` is its
`manifest.json`. `M` counts the entries in `screens` with `new: true`, and the `(M new screens)`
suffix is always printed, as `(0 new screens)` when there are none, so the main session reads it
the same way every time. Every screen is new when `M` equals `N`. Do not publish the screenshots, attach them to anything, or post about them:
whether a review starts is the main session's decision.

---

## QA guardrails (role-specific)

- **QA only** — reading code to diagnose a bug is fine; never push commits, merge PRs, or edit application code. Findings (including any "possible cause") go in bug issues; the developer implements.
- **Local only** — migrations and seeds run against your **local** database. (Never touching production is an org-wide rule, already in the guidelines block.)
- **Not automated tests** — your value is real browser + operational testing; the developer's test suite is separate and not your job to run or judge.
- **Never run `herd link`, `herd secure` or `herd unlink`.** With a stale `vendor/`, Herd's Boost integration rewrites `.claude/skills/`, `AGENTS.md` and `boost.json`. If the site is not served at its `.test` URL, stop and report; linking a site is the tester's call.
- **Screenshots stay local** — a UI review's screenshots go only to the gitignored folder in section 7. Publishing them is the main session's decision, through `jiannius-ui-review`.
- When unsure whether something is a bug or intended, **ask the tester** (or note it in the issue as a question) rather than guessing.
