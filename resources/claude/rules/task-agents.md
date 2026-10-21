# Hand work to a task agent

This repo ships task agents in `.claude/agents/playbook/`. Each is a narrower assistant with its own instructions and model. When a task matches one of them, hand it over **before** you investigate or edit anything yourself, even when it looks like a one-line fix. A short complaint that something "doesn't work" is a bug report.

| The task | The agent |
|---|---|
| Investigating a bug, failing test or error | `debugger` |
| Reviewing a pull request, branch or diff | `pr-reviewer` |
| Browser-testing a pull request or feature | `qa-tester` |
| Implementing one already-planned fix | `pr-fixer` |
| Executing a whole approved plan, all its phases | `plan-executor` |
| A roadmap, architecture or design proposal | `architect` |
| Writing a data backfill or data fix | `data-backfill` |
| Importing legacy CSV, Excel or PDF data | `data-import` |
| Sweeping the codebase for one class of problem (permissions, validation, accessibility, N+1) | `auditor` |
| A data mismatch, a missing sync, or a report that won't tie out | `data-investigator` |
| Updating a shared dependency, such as Atom, in this project | `dependency-updater` |

This holds even when a skill also matches: a review request goes to `pr-reviewer` although `jiannius-dev` covers review. If an agent is not among the agents available to you, because this repo or you opted out of it, do the work yourself under the rules in `AGENTS.md`. A person's explicit instruction about how to do a task wins over this table. If you are one of these agents, do not pass the task on; report back to whoever called you.

## After a QA report

In an interactive Claude Code session only: when a QA report — from `qa-tester`, or from the `jiannius-qa-tester` skill in this session — ends with `UI changes: yes — N after screenshots in <dir>, manifest <file>`, ask the person once:

> This involves UI changes — review it? I'd take the "before" shots, put both on a private claude.ai page for you to comment on, and keep a status comment on the PR.

On a yes, run the `jiannius-ui-review` skill here, in this session; never hand it to an agent, because an agent cannot receive the comments. On a no, or no answer, leave it, and don't ask again about that PR. Don't ask while a UI review of that PR is already open in this session: its own rounds end with the same line. If the skill is not available to you, say so rather than running a review by hand.

<!-- Managed by jiannius/playbook and rewritten by boost:update. Change it in the package, or opt out in config/playbook.php and keep your own copy outside this directory. -->
