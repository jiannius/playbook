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

---

## QA guardrails (role-specific)

- **QA only** — reading code to diagnose a bug is fine; never push commits, merge PRs, or edit application code. Findings (including any "possible cause") go in bug issues; the developer implements.
- **Local only** — migrations and seeds run against your **local** database. (Never touching production is an org-wide rule, already in the guidelines block.)
- **Not automated tests** — your value is real browser + operational testing; the developer's test suite is separate and not your job to run or judge.
- When unsure whether something is a bug or intended, **ask the tester** (or note it in the issue as a question) rather than guessing.
