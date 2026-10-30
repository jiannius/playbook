# Use-case file format

One file per feature area, at `docs/use-cases/<area>.md`. This is the format `jiannius-use-cases`
writes and `qa-tester` reads.

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

## IDs

A short uppercase area prefix and a two-digit number: `CL-01`, `INV-03`. IDs are stable: never
renumber. A removed use case's ID is retired, and a new one takes the next number.

## The index

`docs/use-cases/README.md` holds the app name, a table of the areas, and one line saying the files
are the source of truth and the qa-tester reads them.

| Area | File | Prefix | Use cases | Traced at |
|---|---|---|---|---|
| Invoices | `invoices.md` | INV | 12 | `a1b2c3d` |
