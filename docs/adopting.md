# Adopting playbook in an existing app

Six steps, about twenty minutes. Written from doing it to the app skeleton and from surveying the
first host app — every trap below is one a real repo actually had, not one worth guarding against in
principle.

The [specification](playbook-install.md) says *why* the system is shaped this way. This says what to
type.

## 0. Pre-flight: four commands that tell you what you are in for

Run these before starting. They decide which inputs you pass in step 5, and they are much cheaper to
read now than as a red build later.

```bash
php -r 'echo json_decode(file_get_contents("composer.json"))->require->{"laravel/framework"}, PHP_EOL;'
composer audit --locked --format=summary
git check-ignore -v .claude/skills 2>/dev/null || echo 'not ignored — good'
git branch -a | grep -E '(^|/)dev$' || echo 'no dev branch'
ls tests/Browser 2>/dev/null || echo 'no browser tests'
```

| What you see | What it means |
|---|---|
| `laravel/framework` below `^13.0` | **Stop.** playbook requires `illuminate/support ^13.0`. Upgrade first; there is no partial adoption |
| Advisories reported | You will want `check-audit: false` for the first PR, plus an issue to clear them |
| `.claude/skills` is ignored | Step 3 is not optional for you. This is the most common finding |
| No `dev` branch | Pass `base-branch: main` in step 5 |
| `tests/Browser` exists | Pass `browser-tests: true`, or the suite dies on `PlaywrightNotInstalledException` |

## 1. Install the package

```bash
composer require --dev jiannius/playbook
```

Boost arrives with it — playbook requires `laravel/boost` itself, and pins the version for the whole
fleet. If your `composer.json` also names `laravel/boost` in `require-dev`, **remove it**: two root
constraints on the same package means this repo, not playbook, decides which Boost you are on.

```bash
composer remove --dev laravel/boost --no-update && composer update --lock
```

Expect Boost to move to at least **2.4.7**. Below that version `SkillWriter` drops the trailing
newline from every skill it installs, so the installed copy is the packaged one minus its last byte
— and `playbook:check` will tell you so.

## 2. Enable it in `boost.json`

Two edits, and the second is the one people miss:

```json
{
    "packages": ["jiannius/atom", "jiannius/playbook"],
    "skills": ["laravel-best-practices", "livewire-development", "pest-testing", "jiannius-dev", "jiannius-qa-tester"]
}
```

A repo that adds the package to `packages` but leaves `skills` empty or absent gets the guidelines
and **silently no skills**, on every `boost:update`, forever, with success reported each time. The
`skills` key is the on-switch, not the inventory — `boost:update` installs whatever it discovers as
long as the list is non-empty. `playbook:check` exits 2 if you get this wrong.

## 3. Stop gitignoring the skills

Check first:

```bash
git check-ignore -v .claude/skills
```

If that prints a rule, fix it. `.gitignore` lines like `/.claude` are common and they break the
entire delivery mechanism: Boost writes the skills, git discards them, and a teammate who clones the
repo gets none of them. The QA skill in particular exists to reach someone who never runs composer.

Ignore only the per-person files:

```gitignore
# Per-person Claude state only. NOT .claude/skills/ — those are committed on purpose,
# so a teammate who only ever runs `git clone` still gets them.
/.claude/settings.local.json
/.claude/worktrees
/.claude/projects
```

`playbook:check` exits 2 on an ignored-and-untracked skills path. Ignored *and* force-added is
accepted — a tracked file is committed whatever the pattern says.

## 4. Render and commit

```bash
php artisan boost:update
php artisan playbook:check     # expect: exit 0
```

Then commit both halves — `CLAUDE.md` (or `AGENTS.md`, per your enabled agents) **and**
`.claude/skills/`.

What the exit codes mean:

| Exit | Meaning | Fix |
|---|---|---|
| 0 | Everything current | — |
| 1 | Stale: agent file behind, or an installed skill missing or drifted | `boost:update`, commit |
| 2 | Misconfigured: no `skills` list, ignored skills path, Boost not set up | Steps 2 and 3. `boost:update` cannot fix this |

## 5. Call the shared CI

Replace the body of your test workflow with a call. Keep the **file name and the `name:`** if
anything keys off them — `workflow_run: workflows: [tests]` matches a workflow's *name*, so renaming
it silently stops whatever it triggers, deploys included.

```yaml
name: tests

on:
  push:
    branches: [main]
  pull_request:
    branches: [dev, main]

jobs:
  ci:
    uses: jiannius/playbook/.github/workflows/laravel-ci.yml@v0
    with:
      base-branch: main      # only if this repo has no dev branch
      browser-tests: true    # only if tests/Browser exists
      check-audit: false     # only while clearing existing advisories — with an issue to come back
    secrets: inherit
```

Everything else is left at the shared defaults: PHP 8.4, Node 22, MySQL 8.0, `dev` as the
integration branch, assets built, schema checked from empty, `playbook:check` and the secret scan
on. Full input list is in the [workflow's own header](../.github/workflows/laravel-ci.yml).

Delete a separate lint workflow if you have one — `pint --test` runs as a step here, and a second
job costs a whole billed minute to repeat it.

## 6. Open the first PR and read it

The run should show these, in this order:

```
Pull request must target <base>   ← skipped when correct
Secret scan                        ← gitleaks, on the tracked tree only
Code style                         ← pint --test
Migrations apply from empty        ← migrate:fresh --seed on MySQL
Tests                              ← the whole suite, on MySQL
Guidelines are current             ← playbook:check
Dependency advisories              ← high and critical block
```

Two failures that are not what they look like:

- **The base-ref guard failing on a correctly-targeted PR.** `github.base_ref` comes from the event
  payload, so retargeting a PR produces no new run and the old one keeps reporting the base it was
  created with. Re-running replays the same payload. Push a commit, or close and reopen.
- **A job that dies in about two seconds having run no steps.** That is GitHub Actions billing on a
  private repo, not your code. Check the run annotation.

## Afterwards

- The two repo-scoped skills — `jiannius-dev`, `jiannius-qa-tester` — are now in the session for
  anyone working in this repo, from `.claude/skills/`. The marketplace copies are mirrors of the
  same text and can be uninstalled once every repo a person opens has adopted the package.
- Every `composer update` re-runs `boost:update` through `post-update-cmd`, so the guidelines and
  skills stay current on their own. CI catches it when they do not.
- Anything you turned off in step 5 (`check-audit`, `check-secrets`) is a note to come back, not a
  decision. Put it in an issue.
