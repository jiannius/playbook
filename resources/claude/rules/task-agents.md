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

<!-- Managed by jiannius/playbook and rewritten by boost:update. Change it in the package, or opt out in config/playbook.php and keep your own copy outside this directory. -->
