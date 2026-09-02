# jiannius/playbook

The Jiannius engineering standard, and the machinery that installs and enforces it.

Delivery is **Laravel Boost's job**, not ours. Boost already writes to nine agents
(Claude Code, Codex, Cursor, Copilot, Amp, Junie, Kiro, Gemini, OpenCode) and already
discovers guidelines shipped inside composer packages. Playbook ships content in that
format and adds only the enforcement Boost lacks. See
[`docs/playbook-install.md`](docs/playbook-install.md).

## What lives here

| | |
|---|---|
| **Org rules** | Production is off-limits, where secrets go, product naming, company voice — shipped as Boost guidelines |
| **Dev workflow** | Changing an existing Laravel project: branch off dev, Conventional Commits, review |
| **QA** | Setting up a PR on Herd, browser-testing, filing or approving |
| **`playbook:check`** | The CI gate. Fails when a repo's agent files are stale, when its installed skills have drifted, and when they are gitignored so a clone would never see them |
| **Reusable CI** | `uses: jiannius/playbook/.github/workflows/…@v0` |
| **Hooks** | Claude Code hook definitions, rendered into `.claude/settings.json` |
| **Adoption** | [`docs/adopting.md`](docs/adopting.md) — six steps to put an existing app on the standard, and the traps real repos actually had |
| **The map** | This README — where each piece of the system lives and why |
| **Onboarding** | `ONBOARDING.md` — what to do in your first hour |

## Two routing questions

Everything in this system is placed by answering two independent questions. Get them
in the right order: **what** first, then **how it travels**.

### 1. What is this fact about?

> **If I fix this sentence today, who should get the fix?**

| Answer | Goes in |
|---|---|
| Every repo, `atom` or not | here — `jiannius/playbook` |
| Only repos on this `atom` version | `jiannius/atom` |
| Only this one codebase | that repo's own constitution, below the managed block |
| Only repos cloned from now on | wrong home — that is a skeleton copy, and it will drift |

### 2. Does the work happen inside a repo?

> **Is this useless outside a repo?**

**Composer is repo-scoped. The marketplace is user-scoped.** A plugin installed from
the internal plugin marketplace is available in *every* session, in a repo or not. A composer
package only exists where there is a `composer.json`.

The marketplace is therefore the **superset**, and the rule follows:

| Answer | Channel |
|---|---|
| Yes — there is nothing to act on without a repo | composer, here |
| No — it is also needed with no repo in sight | the internal plugin marketplace |

Worked example. A salesperson drafting a proposal for an existing client works in that
repo's `marketing/` folder; the same person chasing a brand-new lead has no repo at all.
The *skill* must reach both, so it ships via the marketplace. The *client context* — tier,
what shipped, what the constitution says — only exists in the repo, and arrives from that
repo's own `CLAUDE.md`. Skill portable, context local, and they compose.

## What does not live here

| Where | Carries | Scope |
|---|---|---|
| `jiannius/atom` | Laravel / Livewire conventions, linter rulesets | Repos on `atom` — must version-lock with its own code |
| the internal plugin marketplace | Monthly claims, sales, marketer, customer service, business development, email, output styles | **Every session, repo or not** — work that has no codebase to act on |
| the app skeleton and the package skeleton | Subscriptions, permissions, empty constitution stub, empty `marketing/` | Clone-time scaffolding, never convention text |
| claude.ai → Organization instructions | Org rules, for teammates with no repo | A settings surface, not a repo |

the internal plugin marketplace is deliberately Claude-specific and should stay that way. Vendor
neutrality is a *developer* concern — it exists so a developer who switches editors keeps
the standard. The skills there serve people who are not writing code and are not shopping
for editors. `jiannius-output-styles` makes the point: output styles are a Claude Code
feature with no cross-vendor equivalent, and it ships no skill at all.

If a `codex-plugins` is ever needed, keep the skill **content** in one plain repo and make
each vendor-plugins repo a thin packaging layer over it. Two full copies of the same skills
will disagree within a quarter.

## Migrations in flight

| Skill | From | To | State |
|---|---|---|---|
| `jiannius-dev` | the internal plugin marketplace | `resources/boost/skills/jiannius-dev` | **Landed here.** Marketplace copy still live |
| `jiannius-qa-tester` | the internal plugin marketplace | `resources/boost/skills/jiannius-qa-tester` | **Landed here.** Marketplace copy still live |

Both are useless outside a repo, so composer is the right channel. They stay in **both** places
until `composer require jiannius/playbook` actually resolves and the repos have installed it —
removing the marketplace copy first would leave everyone with neither.

**The QA-composer question is settled.** The earlier worry was that a QA person might not have
`composer install` run, and so would never see a skill shipped in `vendor/`. Two things close it:
Boost's `SkillWriter` copies each skill out of `vendor/` into **`.claude/skills/<name>/`**, which is
a committed directory — so a plain `git clone` carries it. And step one of the QA job is
`composer install` + `npm run build` to get the PR running on Herd, so anyone who can do the work
at all already has composer.

That does depend on `.claude/skills/` being **committed, not gitignored**. Keep it that way — it is
what makes these skills reach a teammate who never runs composer.

Everything else in the internal plugin marketplace stays. Seven of its nine plugins are work with no
codebase attached.

## The line this README must hold

It names locations and decision rules. It must **never** restate a convention — a second
copy of a convention is the problem this repo exists to prevent.

## Status

The specification is [`docs/playbook-install.md`](docs/playbook-install.md) — the source of truth
for delivery and enforcement. Against it:

**Built** — org rules as a Boost guideline (`resources/boost/guidelines/core.blade.php`);
`playbook:check` and its tests; four reusable CI workflows; the two repo-scoped skills at
`resources/boost/skills/`. On Packagist since 2026-08-30 — `v0.2.1`, with `v0` tracking it, so
`composer require --dev jiannius/playbook` resolves.

**Verified end to end** — the app skeleton's wiring, jiannius/skeleton-project#4: it takes the
package, names it in `boost.json` `packages`, seeds the `skills` key, commits `.claude/skills/`,
and swaps its `tests.yml` for a call into `laravel-ci.yml@v0`. On 2026-09-02 that call went green
in a private repo — pint, `migrate:fresh --seed` from empty on MySQL 8.0, 39 tests / 91 assertions
including the Chromium browser suite, and `playbook:check` reporting the guidelines current. First
run of the delivery chain outside Testbench fixtures, and the first repo whose `dev` branch exists,
which the base-ref guard has always assumed. Merged to `dev` the same day.

**Unblocked** — the GitHub Actions billing stop cleared on 2026-09-02. While it held, no runner
would accept a job in a *private* org repo: *"recent account payments have failed or your spending
limit needs to be increased"*. Jobs were created, assigned nothing, and failed in two seconds
having run no steps. Public repos are unmetered, so playbook's own CI never saw it. Worth keeping
in the record: a job that dies that fast, before checkout, is a billing state and not a code result.

**Next, in order** — retire the two marketplace copies once the live repos have installed the
package, not just the skeleton; paste the org instructions; then hooks and the
`permissions.deny` render target.

[`docs/adopting.md`](docs/adopting.md) is written and verified against the first host app: it is the
repo-facing guide, six steps with the pre-flight commands that decide the inputs. `ONBOARDING.md` —
the *person*-facing one, what to do in your first hour — is still not written.

New teammate? Start with *How We Stay Aligned* — the four repos, what each is for, and what to
do day to day whether or not you write code:
https://claude.ai/code/artifact/91e6d9af-58a9-4af6-a84b-9f574a5fc861
