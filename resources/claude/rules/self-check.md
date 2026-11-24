# Every pull request carries a self-check

After you open a pull request, or push to one, and before you tell anyone it is ready or ask for review:

1. Run `pr-reviewer` on it, and fix any "request changes".
2. If it touches the UI (views, Livewire, CSS or JS), run `qa-tester`, and fix what it reports.
3. Wait for CI to go green: `gh pr checks <n> --watch`. If it is red, fix it, push and wait again.
4. Add a `## Self-check` section to the PR **description**, not a comment: the reviewer's verdict and requirement check, the QA result or "n/a — no UI", "CI green", and whether you checked it by hand.

A PR without that section is not ready. Skip the self-check only for a docs-only or config-only PR, or for the release PR (the integration branch into `main`). The full steps are in `jiannius-dev`, step 8.

If you are a task agent (`pr-fixer`, `plan-executor` and so on), do not run the self-check yourself: report back, and the main session runs it.
