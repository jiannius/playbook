# Working on playbook

## After every release, draft a WhatsApp to the team

When a playbook release is published (a `vX.Y.Z` tag with its GitHub release), draft a WhatsApp
message to the team with the `jiannius-whatsapp` skill. That skill shows the draft and sends only
when TJ confirms.

**Who:** every member of the `dev` and `dev-leader` teams in the jiannius org, except the person
sending it:

`gh api orgs/jiannius/teams/dev/members -q '.[].login'` (and the same for `dev-leader`)

GitHub logins map to WhatsApp names as follows. If a login isn't listed, ask TJ who it is rather
than guessing, then add it here.

| GitHub login | WhatsApp name |
|---|---|
| `tjloong` | TJ |
| `travissootho` | Travis |
| `emily123-jia` | Emily |

**What to say:** a few short lines in plain words, no markdown tables:

- The version, and in one or two sentences what changed for them.
- What they have to do, taken from the release's **Upgrading** section: nothing, run
  `boost:update`, raise the constraint, or restart Claude Code. Say "nothing to do" when that's true.
- The release link.

Send one message per person, unless TJ says to use a group. Skip a release only when TJ says so.
