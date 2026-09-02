# Delivery and enforcement — specification

How the standard reaches a repo, what Laravel Boost already handles, and the three
gaps `playbook` fills.

Status: **adopted and delivering.** Guidelines, `playbook:check`, the four reusable workflows and
the two skills exist; the package resolves from Packagist; and the app skeleton installs it and
runs the whole chain in CI. See the README's Status for where each piece stands and what is next.

> **Revised 2026-08-28.** An earlier draft specified a full renderer — agent registry, multi-target
> output, managed markers, idempotency rules. Reading `vendor/laravel/boost` showed most of that
> already exists and is maintained by Laravel. This version rides Boost instead of duplicating it,
> and `playbook`'s own command shrinks to the three things Boost does not do.

## Purpose

`jiannius/playbook` carries the team's standard: org rules, QA / review / audit skills, PR and
issue procedure, and the reusable CI that enforces them.

Delivery is **Laravel Boost's job**. `playbook` ships content in the format Boost already
consumes, and adds only the enforcement Boost lacks.

## What Boost already does

Verified against `laravel/boost ^2.3` as vendored in the app skeleton, 2026-08-28.

**Nine agent targets**, each with a configurable output path — `src/Install/Agents/`:

| Agent | Writes |
|---|---|
| ClaudeCode | `CLAUDE.md` |
| Codex | `AGENTS.md` + `.codex/config.toml` |
| Cursor · Copilot · Amp · Junie · Kiro · Gemini | `AGENTS.md` |
| OpenCode | `AGENTS.md` + `opencode.json` |

```php
config('boost.agents.claude_code.guidelines_path', 'CLAUDE.md')
config('boost.agents.codex.guidelines_path', 'AGENTS.md')
```

Adding Codex support later is a config entry, not code. It is supported today.

**Package guideline discovery, keyed by major version** — `src/Install/GuidelineComposer.php:194`:

```php
$packageGuidelines = $this->guidelinesDir($guidelineDir.'/'.$package->majorVersion());
```

Any composer package can ship `resources/boost/guidelines/*.blade.php` and Boost composes
it into every enabled agent's file. **`jiannius/atom` already does this** — 386 lines at
`resources/boost/guidelines/core.blade.php`, live in the vendor directory of every app that
installs it.

**Auto-run on update.** The app skeleton's `composer.json` already carries:

```json
"post-update-cmd": [
  "@php artisan vendor:publish --tag=laravel-assets --ansi --force",
  "@php artisan boost:update --ansi"
]
```

A dependency's own composer scripts never run — only the root package's do — so this entry in the
consuming app is the mechanism. Confirmed: `laravel/boost`'s own `scripts` block contains only its
internal lint and test tasks.

## What playbook therefore does *not* build

- **No renderer for rules.** Ship `resources/boost/guidelines/*.blade.php`, exactly as
  `atom` does. `boost:update` delivers it to all nine agents.
- **No agent registry.** Boost's is better and maintained by Laravel.
- **No `AGENTS.md` / `CLAUDE.md` symlink.** Boost writes both files independently as separate
  targets with the same composed content. A symlink would conflict — Boost would write through
  the link, then write the target again. *(The earlier draft got this wrong.)*

The underlying finding that motivated the symlink still holds and is worth keeping on record —
Claude Code 2.1.247 does not read `AGENTS.md`:

| Setup | Rule was read |
|---|---|
| `AGENTS.md` alone | **no** |
| `CLAUDE.md` alone | yes |

Boost's `ClaudeCode` agent handles this correctly by writing `CLAUDE.md` directly. No workaround
needed.

## What playbook *does* build

Three gaps, in priority order.

### 1. `playbook:check` — the CI gate

**Boost has no verify or dry-run mode.** Nothing in `src/Commands/` offers one. This is the real
gap, and it is what makes drift enforceable rather than merely discouraged.

```
playbook:check          # verify only; write nothing; exit non-zero if stale
playbook:check --diff   # print the unified diff per agent file
```

Behaviour:

- Compose the guidelines in memory via Boost's `GuidelineComposer`.
- Compare against each enabled agent's `guidelines_path` on disk.
- Verify the **skills** too, per enabled agent, at that agent's own `skillsPath()`: installed at
  all, byte-identical to the packaged source, and **reachable by a clone** — matched by a
  `.gitignore` rule and untracked means Boost writes it and git throws it away.
- Exit `0` — every file matches what Boost would produce now.
- Exit `1` — any file stale. Print the diff and the remedy: `run boost:update and commit`.
- Exit `2` — the check itself failed (unreadable config, missing Boost), **or the repo is
  configured so the skills can never arrive**: no `skills` list in `boost.json`, or an ignored and
  untracked skills path. Distinct from `1` because `boost:update` cannot fix either one.
- Writes nothing, under any circumstance.

The skills half was added after the first host app was surveyed: it had `/.claude` in its
`.gitignore`, which means every skill Boost installs there is discarded on commit and a teammate
who clones gets none of them. Nothing reported that — not `boost:update`, which succeeds, and not
this command, which only looked at agent files. Ignored *and tracked* is accepted, because a
force-added file is committed whatever the pattern says, and a false alarm there teaches people to
stop reading the check.

This ships in the reusable workflow in this same repo, which is why the two live together: the
check and the thing it checks can never disagree about the format.

### 1b. What else the reusable CI must do

`playbook:check` is not the only job in the workflow. Working out how two people run parallel
branches surfaced three more checks, and the useful part was discovering that **most of it belongs
in CI rather than in a written rule.**

| Check | Replaces the rule | Notes |
|---|---|---|
| `migrate:fresh --seed` on every PR | "whoever merges second must test the combined schema" | Needs a `services:` database in the job |
| PR base branch is `dev` | "branch off dev" | One `if: github.base_ref != 'dev'` guard |
| Branch is current with `dev` | "pull dev daily" | GitHub branch protection setting — no code at all |

The migration one is the reason this section exists. When two branches each run only *their own*
migrations against *their own* database, the combined schema is untested until both land — and
`migrate:fresh` replays in **filename timestamp order**, which need not match merge order. A branch
can pass in isolation and still break a fresh rebuild once the other's migration sits ahead of it.

Stated as a rule, that is something people forget. Stated as a CI job it cannot be forgotten,
because CI always starts from an empty database and runs the whole set in order. Rung 5 beats
rung 1 whenever the check is mechanisable — see "The enforcement ladder".

### 1c. The workflows, as built

Four files in `.github/workflows/`. Consumers **call** them, never copy them.

| File | Trigger | For |
|---|---|---|
| `laravel-ci.yml` | `workflow_call` | Applications. Jobs: `guard` (base-ref) and `ci` |
| `package-ci.yml` | `workflow_call` | Packages — Testbench, no artisan. Matrixes lowest/highest deps |
| `ci.yml` | push + PR | playbook's own CI, calling `package-ci.yml` — dogfooding |
| `tag-major.yml` | `v*.*.*` tag push | Moves the `v1` tag to the new release |

```yaml
jobs:
  ci:
    uses: jiannius/playbook/.github/workflows/laravel-ci.yml@v0
    secrets: inherit
```

**Keep the calling file's `name:` when replacing an existing workflow.** A `workflow_run` trigger
matches on the *name* of the workflow that ran, not its filename. An app whose `deploy.yml` says
`workflow_run: workflows: [tests]` stops deploying the moment the caller is renamed — and it fails
silently, because a trigger that never fires reports nothing. The app skeleton is exactly this
shape. Replace the contents of `tests.yml`, leave `name: tests` alone.

Inputs on `laravel-ci.yml`: `php-version` (8.4), `node-version` (22), `base-branch` (dev),
`build-assets`, `browser-tests`, `check-schema`, `check-playbook`. `check-schema` and
`check-playbook` default true; a repo not yet onboarded to playbook sets `check-playbook: false`
rather than failing every build.

**`browser-tests` defaults false, and an app with `tests/Browser/` must turn it on.** Pest's
browser plugin drives a real Chromium, which the runner does not have; without the download the
suite dies on `PlaywrightNotInstalledException`. It is off by default because the download is slow
and most repos gain nothing from it — but the failure mode for forgetting it is loud and immediate,
which is the right way round. It also implies `npm ci` even when `build-assets` is false, since the
`playwright` CLI comes from the project's own devDependencies.

This is the one input where the reusable workflow is not yet a drop-in replacement for a
hand-rolled `tests.yml`: the app skeleton has browser tests, so its swap depends on this.

**On the database. CI runs MySQL by default.**

A first pass defaulted to sqlite, because the app skeleton's `.env.example` sets
`DB_CONNECTION=sqlite` and its CI runs no service. That was wrong for this fleet: the apps are
MySQL in production — every app checked sets `DB_CONNECTION=mysql` — and
future projects will be too unless genuinely small. **A suite that only ever passes on sqlite
proves less than it appears to**, and the driver gap is exactly where migration DDL breaks.

So the `ci` job attaches a `mysql:8.0` service and points Laravel at it. That works without
touching `.env` because Laravel builds its env repository with `->immutable()`
(`Illuminate\Support\Env::getRepository`), so a variable already in the real environment is
never overwritten by `.env` — exporting `DB_*` via `$GITHUB_ENV` therefore wins. Verified
against the installed framework, not assumed.

Two consequences worth knowing:

- **The service is unconditional.** GitHub Actions cannot attach one behind an `if:`. A project
  running `database: sqlite` pays a few seconds for a container it never connects to. Cheaper
  than maintaining two near-identical jobs.
- **Tests run on MySQL too, not just the migrations.** That is the point, but it is also the
  one change that could surface latent failures in an existing repo whose suite quietly assumed
  sqlite. Those are real bugs rather than CI noise — though a repo mid-migration can set
  `database: sqlite` to defer them.

**`tag-major.yml` is the ladder applied to ourselves.** Consumers pin a major tag while composer
resolves semver, so every release needs two tags and the moving one is exactly what a person
forgets. It is not written as a rule anywhere; it is a workflow that fires on the semver tag.

### 2. Skills — built

Two skills moved in from the internal plugin marketplace because they are useless outside a repo:
`jiannius-dev` (changing an existing Laravel project) and `jiannius-qa-tester` (setting up a
PR on Herd and browser-testing it). Both now live at `resources/boost/skills/<name>/SKILL.md`.

**The QA-composer question is closed.** The worry was that a QA person without `composer install`
would never see a skill shipped in `vendor/`. `SkillWriter::writeNonCustomSkill()` copies the skill
directory out of `vendor/` into `.claude/skills/<name>/` — a committed path, so a plain `git clone`
carries it. And the QA job opens with `composer install` and `npm run build` to get the PR onto
Herd, so anyone who can do the work has composer regardless. The dependency to preserve is that
**`.claude/skills/` must stay committed, not gitignored**.

Both skills stay in the marketplace as well until the package is installable and installed;
removing the marketplace copy first would leave everyone with neither. That is a temporary
double copy, and the README's warning applies — the composer copy is canonical, and the
marketplace copy is not to be edited.

The other seven internal plugin marketplace skills stay where they are. Composer is repo-scoped; the
marketplace is user-scoped and therefore the superset. See the README's second routing
question.

**What `jiannius-dev` gained on the way in.** Only the parts of parallel work that a check
cannot make for you — everything mechanisable went to CI above:

- **When to run two branches at once, and when to stagger.** Parallel is fine when the features
  touch different domains. Stagger when they share hot files or, especially, the same tables. In
  one of our apps, 23 of its 32 migrations landed in a single 90-day window, with churn
  concentrated in a handful of shared files — that profile favours staggering. A quieter
  repo tolerates parallelism better, so this is judgement, not a rule.
- **The worktree recipe.** A second worktree gets no `vendor/`, no `node_modules/` and no `.env`
  (roughly 300 MB and two installs on a typical app), and needs its own `DB_DATABASE` so one
  branch's migrations do not move the other's schema underneath it. Worth shipping as a setup
  script rather than as instructions.
- **Two agents do not share context.** Two Claude sessions on two branches each build their own
  view of the codebase and drift in approach, not only in code. The external standard is what
  keeps them consistent — they follow the same conventions without either knowing the other
  exists. That is a reason the standard has to arrive from outside the session, not a nice-to-have.


**Correction, 2026-08-28.** An earlier draft claimed Boost's only skill source was
`src/Skills/Remote/GitHubSkillProvider.php`. That was wrong — it missed
`Composer::packagesDirectoriesWithBoostSkills()`, which is consumed by both
`Install/ThirdPartyPackage.php:28` and `Install/SkillComposer.php:96`.

**Boost discovers skills shipped inside composer packages, at `resources/boost/skills/`.**
Verified against `laravel/boost 2.7.0` by calling the scanner directly:

```
guidelines discovered: …/vendor/jiannius/playbook/resources/boost/guidelines
skills discovered:     …/vendor/jiannius/playbook/resources/boost/skills
```

So skills need **no copy command and no GitHub repo** — drop them in the directory. The three
options this section used to weigh (a GitHub skill repo, a `playbook:install` copier, or folding
the content into the guidelines as prose) are all moot, and are no longer under consideration.

**One wiring trap, verified against 2.7.0.** Shipping the directory is necessary but not
sufficient — **skills are opt-in per repo, and the opt-in is not `packages`.**

`UpdateCommand` computes `$hasSkills = ! --ignore-skills && ($config->hasSkills() || is_dir('.ai/skills'))`,
and `Config::hasSkills()` is just `getSkills() !== []` reading the `skills` key of `boost.json`.
That key is only ever written by `InstallCommand`, and only when the operator ticked **Agent
Skills** in the install prompt (`setSkills($this->installedSkillNames)` — it stores the list of
installed skill names, which `SkillWriter::sync()` later diffs to evict stale ones).

The consequence: a repo that lists `jiannius/playbook` under `packages` but has an empty or absent
`skills` key gets the guidelines and **silently no skills**, on every `boost:update`, forever. It
is not an error — `boost:update` reports success. The app skeleton's `boost.json` is in exactly
that state today: `packages` names `jiannius/atom`, and there is no `skills` key at all.

So the skeleton wiring is two edits, not one: add `jiannius/playbook` to `packages`, **and** seed a
non-empty `skills` list (or make "run `boost:install` and tick Agent Skills" an onboarding step).

**`playbook:check` now guards this**, exit `2`, because the failure is otherwise invisible in every
repo it hits: the guidelines arrive and look right while the skills silently never do.

**Authoring constraint: no `#` comments inside fenced code blocks in a `SKILL.md`.**
`SkillWriter::copyFile()` pushes every `.md` through `MarkdownFormatter::format()`, so what lands
in `.claude/skills/` is *reformatted*, not copied byte-for-byte. That formatter's heading rule is

```
preg_replace('/(#{1,4} .+)\n(?!\n)/m', "$1\n\n", $content)
```

— unanchored, and with no notion of code fences. A line like `composer install  # if PHP deps
changed` therefore looks like a heading, and the installed copy gains a blank line the source
never had; a six-line block of commented shell commands comes out double-spaced. Verified against
`laravel/boost 2.7.0`, and worth reporting upstream.

Put the explanation in a table beside the block instead — it reads better anyway. `SkillsTest`
asserts the installed copy is byte-identical to the source, so a reintroduced `#` comment fails
CI here rather than quietly degrading what teammates get.

### 3. `.claude/settings.json` — permissions *and* hooks

Two things share one mechanism, because both are keys in the same file.

**Hooks.** A hook is nothing more than an event, an optional matcher, and a shell command:

```json
{ "hooks": { "SessionStart": [{
    "matcher": "startup|clear|compact",
    "hooks": [{ "type": "command", "command": "…", "shell": "bash" }] }] } }
```

The *trigger* is Claude-specific; the *command it runs* is ordinary shell and entirely
vendor-neutral. So the logic belongs in a real command that three things can call:

| Trigger | Reach | Role |
|---|---|---|
| Claude Code hook | Claude Code only | Fast feedback mid-session |
| git `pre-commit` | any editor, any AI, none | Local gate — the vendor-neutral one |
| CI | everyone, unavoidable | The actual gate |

Because the definition is three lines of JSON, hooks do not need a marketplace or a repo of
their own — playbook renders them into `.claude/settings.json` like any other target.

Candidate hooks, and where each really belongs:

| Candidate | Verdict |
|---|---|
| `SessionStart` staleness nudge — "agent files stale, run `boost:update`" | **Keep.** In-session speed; CI stays the gate |
| `PreToolUse` block prod commands — deny `migrate` / `db:*` on a non-local connection | **Keep**, with the caveat below |
| `PostToolUse` lint the edited file | → git `pre-commit`. Covers everyone; the hook is only faster |
| `PreToolUse` secret guard | → `gitleaks` in CI + `pre-commit`. Portable and better |

Caveat on the prod guard: it binds Claude Code only. A teammate in Cursor typing
`php artisan migrate` is stopped by nothing. **The real control is not having production
credentials on dev machines** — the hook is a seatbelt, not a lock.

And note that project-level hooks are gated behind workspace trust: a hook shipped by *any*
route is inert until the teammate's environment trusts it. That is the "silently not
enforced" risk, and the reason CI must remain the real gate.

**Permissions** — proposal, unadopted

The skeleton clones `.claude/settings.json` once, so a future org-wide `deny` never reaches repos
already created. `composer update` alone cannot fix this: `vendor/` is inert — verified, a skill in
`vendor/…/skills/` is not listed by Claude Code while an identical one in `.claude/skills/` is.

JSON has no comments, so the managed region is a **key**, not a marker pair:

```json
{
  "permissions": {
    "deny":  ["…"],
    "allow": ["…"]
  },
  "_playbook": { "version": "1.4.2", "sha": "a1b2c3d4" }
}
```

- `permissions.deny` is owned outright by playbook and replaced wholesale.
- `permissions.allow` and every other key belong to the project, never touched.

Two ceilings to accept before adopting:

1. **A repo-level deny is editable by anyone with commit access.** A deny that genuinely cannot be
   overridden must be admin-deployed managed settings on the machine, which does not ride composer.
2. **It binds Claude Code only.** A teammate in Cursor is not gated. Any org-wide safety rule needs
   a CI check as well — the portable half, and the only half that holds for every editor.

## The enforcement ladder

Why the design is shaped this way. Every rule sits on a rung, and picking the rung matters more
than the wording.

| Rung | Mechanism | Strength | Portable? |
|---|---|---|---|
| 1 | Prose in the agent files | Advisory — usually followed | Yes |
| 2 | Linters — Pint, PHPStan, Rector | Mechanical, blocks CI | **Yes** |
| 3 | Claude Code hooks | Deterministic, fires every time | No |
| 4 | Permissions in `settings.json` | Hard deny | No |
| 5 | CI + branch protection | Blocks the merge | **Yes** |

Two consequences run through every decision in this document:

1. **The two strongest rungs are also the two most portable.** Push enforcement down to linters
   and CI and vendor independence comes free — a teammate on Cursor loses speed, not correctness,
   because CI still blocks the bad merge.
2. **A rule written as prose when it could be mechanical is a weaker rule.** "Use four spaces"
   belongs in Pint, not in a guideline. Reserve prose for what a linter cannot check: the *why*.

Worked example. "Production is off-limits" as a sentence is rung 1. As a permission it is rung 4,
but binds Claude Code only. As a CI check it is rung 5 — the only version that holds for someone
using a different editor.

## Packaging

- `jiannius/playbook` requires `laravel/boost`. Both are `require-dev` — they guide development,
  not runtime.
- The skeletons require only `jiannius/playbook`; Boost arrives transitively.
- Playbook therefore pins Boost's version for the whole fleet. That is the point: one place decides
  which Boost everyone is on. The cost is that a breaking Boost release blocks on a playbook update.
- Skeleton `post-update-cmd` gains one line after `boost:update`, once there is a command to call.

## Settled

- **Org rules — rendered *and* settings-only.** Same source text, two channels: Boost renders it
  into every agent file, and it is mirrored into claude.ai Organization instructions for teammates
  with no repo. The audiences do not overlap. Accepted cost: the mirror is a manual paste with no
  staleness check. *(2026-08-28)*
- **Custom content lives outside any managed block, always.** *(2026-08-28)*
- **No vendor-specific work.** `AGENTS.md` is the general target and Boost already writes it for
  eight of its nine agents. *(2026-08-28)*
- **Test enforcement is ours, not Boost's install-time answer.** Boost asks whether to enforce
  tests during `boost:install` and writes the resulting block into the agent files, but
  `boost.json` does not persist the answer. So every `composer update` runs `boost:update`, which
  recomposes with the answer defaulted off and **silently deletes the block** — no error, no
  failure, the rule simply stops being there. Observed on the app skeleton against
  `laravel/boost` 2.7.0.

  A rule that evaporates on an unrelated command is the exact decay this package exists to stop,
  and the fix is the one the enforcement ladder already prescribes: own the text. It now ships in
  `resources/boost/guidelines/core.blade.php`, which makes it version-controlled, reviewable, and
  enforced by `playbook:check` like everything else here. `tests/Feature/GuidelinesTest.php`
  fails if it goes missing again. *(2026-08-30)*

## Still to confirm


- Nothing outstanding. Skills ship at `resources/boost/skills/`; playbook owns `permissions.deny`.

## Reference

This document is the source of truth for delivery and enforcement. The team-facing overview —
what the four repos are for, and what to do day to day — is *How We Stay Aligned*:
https://claude.ai/code/artifact/91e6d9af-58a9-4af6-a84b-9f574a5fc861
