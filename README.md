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
| **`playbook:check`** | The CI gate. Fails the build when a repo's agent files are stale |
| **Reusable CI** | `uses: jiannius/playbook/.github/workflows/…@v0` |
| **Hooks** | Claude Code hook definitions, rendered into `.claude/settings.json` |
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

| Skill | From | To | Why |
|---|---|---|---|
| `jiannius-dev` | the internal plugin marketplace | here | Useless outside a repo |
| `jiannius-qa-tester` | the internal plugin marketplace | here | Needs the PR checked out — but confirm a QA person has composer installed first |

Everything else in the internal plugin marketplace stays. Seven of its nine plugins are work with no
codebase attached.

## The line this README must hold

It names locations and decision rules. It must **never** restate a convention — a second
copy of a convention is the problem this repo exists to prevent.

## Status

Nothing is built yet. Current work is the delivery and enforcement specification:
[`docs/playbook-install.md`](docs/playbook-install.md).

New teammate? Start with *How We Stay Aligned* — the four repos, what each is for, and what to
do day to day whether or not you write code:
https://claude.ai/code/artifact/91e6d9af-58a9-4af6-a84b-9f574a5fc861
