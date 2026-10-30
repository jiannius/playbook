# Working on playbook

## After every release, draft a WhatsApp to the team

When a playbook release is published (a `vX.Y.Z` tag with its GitHub release), draft one WhatsApp
message to the **Jiannius Team** group with the `jiannius-whatsapp` skill. Find the group's chat id
with the skill's *Finding a chat* step. Show TJ the draft and send it only when he confirms.

**What to say**, in one message short enough to fit before WhatsApp's *Read more*:

- The version, and which repos are on it already.
- How to adopt it, as steps the reader can say to their own Claude session. Take them from the
  release's **Upgrading** section, for example "pull the latest dev", then restart Claude Code
  once. Say "nothing to do" when that's true.
- If the release changes how they work, one or two lines on what's new for them.
- The release link.

Skip a release only when TJ says so.

## This repo is public: name no other repo

Anything that lands on GitHub here is world-readable: files, commit messages (a squash merge copies
the branch's commit messages into its body), PR titles, bodies and comments, issues and release
notes. Never name another repo or org in them, whether a client's or one of our own products. Write
"a consumer app", "a client repo" or "the app skeleton" instead. Naming the team is fine. Before
every commit and every `gh` write here, check the text for repo names.
