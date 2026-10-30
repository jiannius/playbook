---
name: jiannius-use-cases
description: "Generate or update a Jiannius app's use cases in docs/use-cases/ from its code and GitHub issues, and publish a page to read them. Say 'generate the use cases'."
---

# Generating an app's use cases

While this skill is active you are writing down **what a Jiannius app lets people do**, one use case
at a time, traced from the code and the GitHub issues. You are assisting a developer, in their
session, in the repo of the app.

> This skill arrives from `jiannius/playbook` and is rewritten by `boost:update`. Edit it in that
> package, never in `.claude/skills/` — a local edit is overwritten on the next update.

The universal rules — production is off-limits, secrets and customer data, ask when unsure — come
from the guidelines block of this repo's agent file. Branch, commit and PR rules belong to
`jiannius-dev`. This skill does not restate them.

## 1. What this is for

The use cases feed two readers:

- **`qa-tester`** runs the use cases a PR touches, instead of inventing a test plan from the PR text.
- **New developers** read them to learn what the app does before they read the code.

**The Markdown in `docs/use-cases/` is the source of truth.** The page you publish at the end is a
reading view and nothing more. If the two disagree, the Markdown wins.

## 2. Pick the mode

| Mode | When | What you do |
|---|---|---|
| **Generate** | `docs/use-cases/` does not exist | Find the areas, trace all of them, write the folder |
| **Update** | The folder exists | Re-trace only the areas whose code changed since the sha stamped in their file (`git diff --stat <sha>..HEAD`), or the area the developer named |
| **Seed** | The developer points at an existing artifact or doc | Read it (the Artifact tool's `read` action, or the file), convert it to the format below, then verify each use case against the code |

If the developer's words do not make the mode clear, say which one you will run and why.

## 3. Find the areas

Read the navigation, the routes and the Livewire pages. Group them into **feature areas** a person
would name: invoices, clients, settings, not controllers. Propose the list, each with a short
uppercase prefix (`INV`, `CL`) and a one-line scope.

**Confirm the list with the developer before you trace anything.** Merging two areas or dropping one
is cheap now and expensive after the files are written. On an update, name the areas you will
re-trace and why.

## 4. Gather the GitHub history once

You gather this, not the tracers, so it is fetched a single time:

```bash
gh issue list --state all --limit 200 --json number,title,labels,state
gh pr list --state merged --limit 200 --json number,title
```

Sort the issues and PRs by area. Give each tracer only the ones that touch its area. A closed bug is
an **edge case** the code now handles: put it under **Edge cases** and link it under **Issues**.

## 5. Trace each area

Spawn **one subagent per area**, in parallel, in a single message. Set **model: `sonnet`** and
**effort: `medium`** on every one of them, explicitly: the Agent tool only sets effort when you ask,
and the tracers do not need more.

If you have no Agent tool, trace the areas yourself, one at a time.

Each brief carries:

- The format (the block below) and the area's prefix and scope.
- The area's entry points: routes, Livewire components, models, policies.
- The area's issues and merged PRs from step 4.
- The existing area file and its IDs, on an update, so IDs stay stable.
- What to return: **the area file's Markdown**, and **a separate list of gaps**, each with a
  severity (high, medium or low) and a `path:line`.

Tell them to **read the code, not guess**. Every permission claim cites the `path:line` of the gate
that enforces it. A flow they cannot find in the code is a gap, not a use case.

### The format

```markdown
# <Area name>

<one-line scope of the area>

Traced at `<short sha>` on <YYYY-MM-DD>.

## <PREFIX>-01 <Goal, as a verb phrase: "Void a paid invoice">

- **Actor:** <role(s) who do it>
- **Given:** <data to seed and who to log in as>
- **Trigger:** <where it starts: page, button>
- **Flow:** <steps, joined with →>
- **Edge cases:** <variations, validation, empty/blocked states>
- **Outcome:** <what changes: records, status, mail, files, audit>
- **Permissions:** <who may, who may not, with `path:line` of the gate>
- **Tests:** <test files that cover it, or "none">
- **Issues:** <#123 short description, … — optional>
```

`template.md`, next to this file, holds the same block. IDs are a short uppercase prefix and a
two-digit number (`CL-01`, `INV-03`). **IDs are stable: never renumber.** A removed use case's ID is
retired, and a new one takes the next number.

## 6. Write the files

Write one `docs/use-cases/<area>.md` per area, from what the tracers returned. Stamp each with the
short sha of `HEAD` (`git rev-parse --short HEAD`) and today's date.

Then write `docs/use-cases/README.md`: the app name, a table of the areas (file, prefix, use-case
count, traced-at sha), and one line saying the files are the source of truth and `qa-tester` reads
them.

On an update, rewrite only the areas you re-traced, keep every existing ID, and refresh the index
row for each.

## 7. Settle the gaps

A gap is something the tracers found that does not hold together: a screen with no permission check,
a flow that dead-ends, a validation the issue asked for and the code lacks.

**Gaps never go into the use-case files.** They go in a list for the developer.

1. **Confirm every high-severity gap in the code yourself** before you show it. Open the file at the
   `path:line` the tracer gave and check the claim.
2. Present the list, each gap marked **confirmed** or **unconfirmed**, with its severity and
   `path:line`.
3. **File GitHub issues only when the developer says so**, and only for the gaps they name.

## 8. Publish the reading page

If you have no Artifact tool in this session, skip this step and say so.

The page is **private, under the developer's own claude.ai account**, and it is **generated from the
Markdown, never edited by hand**. **Never write its URL into the repo.**

1. Look for the developer's existing page: call the Artifact tool's `list` action and match the
   title `<App name> use cases`.
2. Found: republish to its URL. Not found: publish a new one.
3. Load the `artifact-design` skill first, then build the page:
   - A header with the app name, the traced-at sha and the counts.
   - One tab per area.
   - Search across every field of every use case.
   - One card or row per use case, with its ID.
4. The title is exactly `<App name> use cases`, so the next run finds it.

## 9. Hand off

Tell the developer to commit `docs/use-cases/` on a branch and open a PR like any other change.
Gaps stay in the list you showed them, not in the PR.

---

## Guardrails (role-specific)

- **Read-only on application code.** You write `docs/use-cases/` and nothing else.
- **Never production data.** Seed notes in **Given** describe local data, not real records.
- **No customer data or secrets** in the files or the page: no real names, emails, tokens or keys.
