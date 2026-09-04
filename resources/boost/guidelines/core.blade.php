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

**If this repo implements a product, its README opens by naming that product and saying in a
sentence what it is.** The organisation instructions carry only the index of what exists — what
each product is called. Everything past the name is this README's job, because it is the copy the
people building the product own, and the only one that cannot drift from the thing it describes.

**Write it for someone reading it on github.com who will never clone this repo.** Most of the
people who need to know what a product is called and what it does are in sales, marketing or
support. They will open the README in a browser, not a terminal, and the answer has to be in the
first screen — above the install steps, before any command they will not run. A README that
explains the product only by way of setting it up has not answered the question.

**Give the product's public URL there too**, on the same first screen. It is the one place
everyone is sent for it, so if the README omits it there is nowhere else to look — and the
failure mode is not a missing answer, it is an invented one. Nobody should ever infer a domain:
a domain that resolves is owned by *someone*, which is no evidence it is ours. Where a URL is
genuinely not settled yet, write that it is not settled. When you need a product's URL or full
name and its README does not give it, use the plain product name and say the detail is not
recorded, rather than reaching for something that looks right.

Two failures make this worth stating rather than assuming. A repo name is not a product name:
`apikan` is a repo, **APIkan** is the product, and handing a client the repo name is a mistake
that has already happened. And a repo whose README is still the framework's default tells a
reader nothing at all — "About Laravel" is not an answer to "what is this".

Keep it current through a rename, and name the old product name while it is still in circulation
rather than deleting it. A retired name goes on appearing in client documents and in habits long
after the code moves, and a reader who cannot connect the old name to the new product is worse
off than one who never saw either.

### Some rules are in both places on purpose

The mirror of the above is also true: **the guardrails at the top of this file — production,
secrets and customer data, when you are unsure — appear in the organisation instructions too**,
in a shorter form with the repo-specific clauses taken out. That is deliberate. Do not resolve it
by deleting either copy.

Composer is repo-scoped, so this file never loads for a teammate working with no checkout in front
of them, and the organisation instructions are the only surface that can carry a customer-data rule
to the person answering a support ticket. Two copies of a rule is normally the exact problem this
package exists to prevent — here it is the price of two audiences that no single channel reaches.

The split is made **clause by clause, not rule by rule**, by asking whether a given sentence means
anything to someone with no repo. "Never expose customer personal data" means something to
everyone. "Migrations and seeders run against local only" does not, so it appears here and nowhere
else. When the two versions differ in detail, the fuller one is the one you are reading; the
shorter one drops what is meaningless outside a repo, never to soften a rule.

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
