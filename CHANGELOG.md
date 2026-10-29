# Changelog

What changed in each release, and what a consuming repo has to do about it. This file ships
inside the package, so it can be read from `vendor/jiannius/playbook/CHANGELOG.md` during an
upgrade. Fuller notes for v0.2.0 onwards are on the
[releases page](https://github.com/jiannius/playbook/releases).

**Before you upgrade:** Composer's caret stops at the next minor for `0.x`, so `^0.5` does not
allow `0.6.0`. A minor release means raising the constraint. A patch release arrives with
`composer update`. Workflows called at `@v0` follow every release automatically.

Upgrading across several minors? Do each minor's **Upgrading** steps in order, oldest first.

## Unreleased

### Changed

- `jiannius-dev`: after opening a PR, the developer runs `pr-reviewer` on it, and `qa-tester`
  too when it touches the UI, then clicks through it by hand (recommended), before requesting
  the QA tester.

### Upgrading

Run `php artisan boost:update` and commit `.claude/skills/`.

## v0.6.3 — 2026-10-29

### Added

- `CHANGELOG.md` ships in the package, with every release's upgrade steps. (#31)

### Upgrading

Nothing to do.

## v0.6.2 — 2026-10-23

### Fixed

- `laravel-ci.yml` runs on `ubuntu-24.04`. `ubuntu-latest` moved to 26.04, which has no Playwright
  Chromium and whose apt mirror 404s. (#30)

### Upgrading

Nothing to do. Re-run any job that failed on ubuntu-26.04.

## v0.6.1 — 2026-10-23

### Fixed

- `laravel-ci.yml` runs `php artisan storage:link` before the Tests step when `browser-tests` is on.
  A fresh checkout has no `public/storage`, so browser tests 404'd on storage assets. (#29)

### Upgrading

Nothing to do.

## v0.6.0 — 2026-10-18

### Breaking

- `playbook:check` verifies Claude Code's task agents in `.claude/agents/playbook/` and the routing
  rule in `.claude/rules/playbook/`. **Exit 1** until `boost:update` has run and the files are
  committed. **Exit 2** if either folder is gitignored, or if one of your own agents has the same
  `name` as one of ours.
- The guidelines changed, so agent files are STALE until `boost:update`.

### Added

- Eleven task agents for Claude Code: architect, auditor, data-backfill, data-import,
  data-investigator, debugger, dependency-updater, plan-executor, pr-fixer, pr-reviewer and
  qa-tester. A Claude Code rule says when to hand work to each.
- `php artisan playbook:sync` writes them. `boost:update` and `boost:install` run it for you.
- `config/playbook.php`: `agents.except` opts out of one agent, `agents.enabled` out of all.

### Changed

- Data investigation and backfills may run against staging when the person asking explicitly gives
  it. Production stays off-limits.
- `claude.yml`: `@claude` replies can no longer start task agents.
- Requires `laravel/pint` `^1.17`.

### Upgrading

1. Raise the constraint to `^0.6` and run `composer update jiannius/playbook -W`.
2. Coming from below 0.5? Do v0.5.0's steps first.
3. Run `php artisan boost:update`, then `php artisan playbook:check`, and expect exit 0.
4. If the check reports a clash with one of your agents, opt out of ours or delete yours.
5. Commit `composer.*`, `AGENTS.md`, `.claude/skills/`, `.claude/agents/` and `.claude/rules/`
   together.
6. Teammates restart Claude Code once after pulling, so the new agents folder is picked up.

## v0.5.1 — 2026-10-12

### Added

- `laravel-ci.yml` inputs `parallel`, `processes` and `opcache`, all off by default. Under
  `parallel`, PHP warnings become failures. (#25)

### Upgrading

Nothing to do. Opt in with `parallel: true` and `opcache: true` under `with:`.

## v0.5.0 — 2026-10-09

### Breaking

- Claude Code's guidelines always go to `AGENTS.md`. From Boost 2.10.1, Boost moved them back to
  `CLAUDE.md` whenever that file existed. Playbook now sets
  `boost.agents.claude_code.guidelines_path` itself. (#23)
- Requires `laravel/boost` `^2.10.3`.

### Changed

- Boost's Test Enforcement block is off by default. Playbook's own "Every change ships with a test"
  rule covers it. A repo's own `config/boost.php` still wins.

### Upgrading

1. Raise the constraint to `^0.5` and run `composer update jiannius/playbook -W`.
2. Run `php artisan boost:update`, then `php artisan playbook:check`.
3. If the check exits 2, make `CLAUDE.md` import `@AGENTS.md` and hold no guidelines block of its
   own: step 4 of [docs/adopting.md](https://github.com/jiannius/playbook/blob/main/docs/adopting.md).
4. Commit `boost.json`. Without it a fresh checkout can't run `boost:update`.

## v0.4.2 — 2026-10-07

- Guidelines: check GitHub for an existing or regressed issue before starting work. (#22)

**Upgrading:** run `php artisan boost:update` and commit the agent files.

## v0.4.1 — 2026-10-06

- New skill `jiannius-wrap-up`, to close a session cleanly. (#21)

**Upgrading:** run `php artisan boost:update` and commit `.claude/skills/`.

## v0.4.0 — 2026-09-30

### Added

- `claude.yml`, a reusable workflow that reviews pull requests and answers `@claude`. It is
  switched on by an optional `CLAUDE_CODE_OAUTH_TOKEN` secret, and skips green without one. (#19)
- It only runs once the caller workflow is on the default branch, so the PR that adds the caller
  is never reviewed by it. (#20)

### Upgrading

Raise the constraint to `^0.4`. Nothing else is required. `claude.yml` is opt-in.

## v0.3.3 — 2026-09-26

- `laravel-ci.yml` input `apt-packages`, for extra system packages. Opt-in.

## v0.3.2 — 2026-09-25

- `laravel-ci.yml` input `timeout-minutes`, default 30, so a hung step can't bill for GitHub's 360.
- `laravel-ci.yml` input `full-suite-on-pr`, default `true`. Set it to `false` to run only the
  secret scan and dependency audit on pull requests. Callers that do must also run CI on pushes to
  their integration branch, or merged code goes untested until `main`. (#18)

## v0.3.1 — 2026-09-24

- qa-tester starts from a clean checkout and hands it back.
- Guidelines: name every PR or issue number, never a bare one, and check for a clean tree before
  branching. (#17)

**Upgrading:** run `php artisan boost:update` and commit the agent files.

## v0.3.0 — 2026-09-24

### Breaking

- `playbook:check` can exit 2 on Boost 2.10+. Boost 2.10 writes Claude Code's guidelines to
  `AGENTS.md`, which Claude Code skips while a `CLAUDE.md` exists. The check fails unless
  `CLAUDE.md` imports `@AGENTS.md` and holds no leftover guidelines block. (#16)
- New guideline sections: find the cause before changing code, show evidence before calling it
  done, plan before a change that spans many files. (#15)

### Upgrading

1. Raise the constraint to `^0.3` and run `composer update jiannius/playbook`.
2. Run `php artisan boost:update`, then `php artisan playbook:check`.
3. If the check exits 2, do step 4 of
   [docs/adopting.md](https://github.com/jiannius/playbook/blob/main/docs/adopting.md).

## v0.2.6 — 2026-09-06

- Guidelines: a product repo's README names its product, and never infer a URL.

## v0.2.5 — 2026-09-03

- The moving `v0` ref is a branch, not a tag. Packagist blocked the re-tagged `v0`. `@v0` callers
  are unaffected. (#13)

## v0.2.4 — 2026-09-02

- Guidelines: three wrong claims fixed. The test rule no longer contradicts `jiannius-dev`, no
  longer names `php artisan test` for repos without artisan, and no longer says CI runs on every
  pull request. (#10)
- `docs/adopting.md`: how to put an existing app on the standard. (#9)
- `laravel-ci.yml` scans for committed secrets with gitleaks. (#8)

## v0.2.3 — 2026-09-02

- `laravel-ci.yml` gates on `composer audit --locked` (`check-audit`, default `true`). Only high and
  critical advisories block. (#6)
- Actions moved off the deprecated Node 20 runtime.
- Package CI tests the lowest dependencies too. Boost below 2.4.7 failed, so the floor is now real.

## v0.2.2 — 2026-09-02

- `laravel-ci.yml` explains why a retargeted PR stays red under the base-branch guard: push a
  commit, or close and reopen, to clear it. (#5)

## v0.2.1 — 2026-08-30

### Fixed

- The "Test Enforcement" rule ships in the guidelines. Boost's own block was deleted by every
  `boost:update`. (#3)

### Upgrading

Every repo reports STALE until it runs `php artisan boost:update` and commits the agent files.

## v0.2.0 — 2026-08-30

### Added

- Skills `jiannius-dev` and `jiannius-qa-tester` at `resources/boost/skills/`. (#1)
- `laravel-ci.yml` input `browser-tests`, off by default. (#2)

### Upgrading

1. Raise the constraint to `^0.2`.
2. Skills need a `skills` key in `boost.json`. Listing the package under `packages` is not enough.
   Run `php artisan boost:install` and tick *Agent Skills*.

## v0.1.0 — 2026-08-29

First release: the Jiannius engineering standard as a Boost guideline, `playbook:check`, and the
reusable CI workflows.
