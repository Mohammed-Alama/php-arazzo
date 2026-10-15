# Issue kinds and the triage state machine

The intake schema (`config/issues/schema.yaml`) carries **kind** on labels, not
on GitHub's native issue types (which stay dark — spec D4/D8). There are exactly
four kinds:

| Kind label     | Meaning                                                  |
| -------------- | -------------------------------------------------------- |
| `kind:feature` | A new capability, or an improvement to an existing one.  |
| `kind:bugfix`  | Something is broken or behaves incorrectly.              |
| `kind:release` | A release task: publish artifacts, promote changelogs.   |
| `kind:chore`   | Maintenance, tooling, cleanup — no user-facing change.   |

Every new-style issue carries **exactly one** kind label (the `Validator`
rejects none, and several) and **exactly one** `level:*` label. The one
exception is a `level:package` issue, which carries **no** kind.

## Levels

The hierarchy is **Package → Epic → Story → Task**, enforced by parent/child
links, not by nesting labels alone:

| Level label     | Meaning                                                       | Parent rule                                                              |
| --------------- | ------------------------------------------------------------- | ------------------------------------------------------------------------ |
| `level:package` | One per package; its body is a generated index of its epics   | must **not** have a parent; exactly one `package:*` label; carries no kind |
| `level:epic`    | Spans multiple stories                                        | must sit under a `level:package`                                         |
| `level:story`   | Child of an epic                                              | must sit under a `level:epic`                                            |
| `level:task`    | Child of a story                                              | must sit under a `level:story`; can never itself be a parent             |

Parent/child is a **real** GitHub sub-issue link (`gh issue create --parent`,
`gh issue edit --parent`) *and* the matching `level:*` label; the two must
agree (spec G5). GitHub applies the kind label itself on web-filed issues; the
intake CI gate adds the `level:*` label a web-filed issue declares only in its
form body.

A `level:package` issue's body is **generated**: `.github/workflows/package-index.yml`
rewrites it from the epics beneath it on structural `issues` events, plus a
nightly sweep. Edit a child, never the index — and text written outside the
`<!-- package-index: generated -->` markers is preserved.

## Why this document exists (decision O2)

`.agents/skills/triage/SKILL.md` is **lock-tracked** in `skills-lock.json` — it
is an upstream (Matt Pocock) skill and **must not be edited**. The `triage`
skill's category roles therefore keep reading `bug` / `enhancement`. The local,
non-locked kind vocabulary maps onto those upstream roles rather than editing
the locked skill:

| Local kind label | Upstream triage category |
| ---------------- | ------------------------ |
| `kind:bugfix`    | `bug`                    |
| `kind:feature`   | `enhancement`            |
| `kind:release`   | *(no upstream counterpart — flows through the same state machine)* |
| `kind:chore`     | *(no upstream counterpart — flows through the same state machine)* |

The legacy `bug` / `enhancement` labels are no longer part of the schema; use
their `kind:*` replacements.

## `kind:release` and `kind:chore` on the state machine

Neither is a separate workflow — both flow through the **same** triage state
machine as the other kinds:

```
needs-triage ──▶ needs-info ──▶ needs-triage   (reporter replied)
             ├─▶ ready-for-agent
             ├─▶ ready-for-human
             └─▶ wontfix
```

A `kind:release` or `kind:chore` issue that conforms to the intake schema is
eligible for `ready-for-agent` exactly like a feature or a bugfix.

## `needs-info` ownership

`needs-info` is owned by the `triage` skill (meaning *"waiting on the reporter"*).
The intake CI gate deliberately **reuses** that label; its meaning is close
enough to be compatible. The gate records its own ownership with an
`<!-- intake-gate -->` marker in the single comment it posts, and only ever
removes `needs-info` when that marker is present and the issue now conforms — so
it can never strip a `needs-info` that `triage` set for a different reason.
