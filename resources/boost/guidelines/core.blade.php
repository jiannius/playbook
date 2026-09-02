## Jiannius

These rules apply to every Jiannius project regardless of stack. They arrive from
`jiannius/playbook` and are rewritten by `boost:update` — edit them in that package, never here.

### Production is off-limits

Never run against, modify, or expose production systems or production data.

- Migrations, seeders, and tests run against **local** environments only. Check the connection
  before running anything that writes.
- Deploys and releases to production are a maintainer's explicit action. Never initiate one.
- Reading code or logs to diagnose a problem is fine. Changing a running system is not.

If a task appears to require production access, stop and say so rather than finding a way.

### Secrets and customer data

- Never print, log, or commit secrets, credentials, API keys, or tokens — not into chat, commits,
  issues, screenshots, or test fixtures. Never ask a person to paste one.
- Never expose customer personal data beyond what the task genuinely needs. Prefer an ID over a
  name, a count over a list.
- If a secret has already been committed, say so immediately. Rotating it is the fix; deleting
  the line is not.

### Product names

Product naming rules are **not** shipped in this package. They live in the organisation
instructions, which reach every conversation — including teammates in sales, marketing and
support, who never open a repo and are the people those rules mostly serve.

If you are writing anything client-facing and unsure which product a piece of work belongs to,
check there rather than guessing.

### Every change ships with a test

Write or update a test for the change, then run it before you call the work done. If something
genuinely cannot be tested, say so plainly rather than leaving it quietly unverified.

- Run the **minimum** set that proves the change while you work — a filename or a filter, not the
  whole suite. Run the **whole suite before you push**, once.
- In an application that is `php artisan test --compact --filter=...`. In a package there is no
  artisan binary, so it is `vendor/bin/pest --filter=...` — the same rule, a different command,
  which is what "regardless of stack" above is asking you to notice.
- Test the behaviour, not the implementation. A test that breaks on every refactor costs more than
  it protects.
- A bug fix starts with a test that fails for the reason the bug exists. Otherwise you cannot know
  the fix worked, only that the symptom stopped.

CI runs the suite on any pull request that touches code, and a repo can narrow that — `paths-ignore`
skips documentation-only runs, and several checks are opt-outable. So do not treat the pipeline as a
guarantee that anything ran. What CI can never do, however it is configured, is **run a test that
does not exist**. Whether a change arrives with one is the part no check can decide for you, which
is why it is written here rather than left to the pipeline.

### CI must stay inside the free allowance

We pay nothing for GitHub Actions and intend to keep it that way. Private repos meter against a
monthly allowance; public repos are unmetered. When you touch a workflow, three facts govern:

- **Every job is billed rounded up to a whole minute.** A five-second job costs the same as a
  fifty-nine-second one. Prefer one job with many steps over many small jobs. A check that is
  not worth a whole minute belongs as a step, not a job.
- **Every matrix leg is another whole minute, on every run, in every repo that calls it.** Add
  one only when the axis genuinely catches something.
- **A superseded run keeps billing until cancelled.** Every workflow needs a `concurrency` group
  with `cancel-in-progress: true`.

Also cheap and worth doing: `paths-ignore` for documentation-only paths, and caching composer
and npm. Never add `paths-ignore` for a path the test suite actually asserts on.

If the allowance runs out, the tell is a job that **fails in about two seconds having run no
steps**, with no log to open — `gh run view --log-failed` answers "log not found", because nothing
ever ran. The run does carry an annotation naming the billing state, so it is not silent; it is
just nowhere near the failure itself, and it reads like a broken build until you go looking.

### When you are unsure

Ambiguity, a suspected bug, or anything outside the task's remit is a question, not a guess.
Ask, or note it explicitly as an open question in your output. A wrong answer delivered
confidently costs more than a question.
