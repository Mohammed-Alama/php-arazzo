# Issue kinds and the triage state machine

The intake schema (`config/issues/schema.yaml`) carries **kind** on labels, not
on GitHub's native issue types (which stay dark — spec D4/D8). There are exactly
three kinds:

| Kind label    | Meaning                                                  |
| ------------- | -------------------------------------------------------- |
| `bug`         | Something is broken or behaves incorrectly.              |
| `enhancement` | A new capability, or an improvement to an existing one.  |
| `chore`       | Maintenance, tooling, cleanup — no user-facing change.   |

Every new-style issue carries **exactly one** kind label (the `Validator`
rejects none, and several) and **exactly one** `level:*` label.

## Levels

| Level label     | Meaning              | Parent rule                                                    |
| --------------- | -------------------- | -------------------------------------------------------------- |
| `level:epic`    | Spans multiple stories | must **not** have a parent                                     |
| `level:story`   | Child of an epic     | must sit under a `level:epic`                                    |
| `level:subtask` | Child of a story     | must sit under a `level:story`; can never itself be a parent     |

Parent/child is a **real** GitHub sub-issue link (`gh issue create --parent`,
`gh issue edit --add-sub-issue`) *and* the matching `level:*` label; the two must
agree (spec G5). GitHub applies the kind label itself on web-filed issues; the
intake CI gate adds the `level:*` label a web-filed issue declares only in its
form body.

## Why this document exists (decision O2)

`.agents/skills/triage/SKILL.md` is **lock-tracked** in `skills-lock.json` — it
is an upstream (Matt Pocock) skill and **must not be edited**. The `triage`
skill's category roles therefore keep reading `bug` / `enhancement`, and `chore`
is added as a third kind through this local, non-locked mapping layer rather
than by editing the locked skill.

## `chore` on the state machine

`chore` is not a separate workflow — it flows through the **same** triage state
machine as the other kinds:

```
needs-triage ──▶ needs-info ──▶ needs-triage   (reporter replied)
             ├─▶ ready-for-agent
             ├─▶ ready-for-human
             └─▶ wontfix
```

A `chore` that conforms to the intake schema is eligible for `ready-for-agent`
exactly like a bug or an enhancement.

## `needs-info` ownership

`needs-info` is owned by the `triage` skill (meaning *"waiting on the reporter"*).
The intake CI gate deliberately **reuses** that label; its meaning is close
enough to be compatible. The gate records its own ownership with an
`<!-- intake-gate -->` marker in the single comment it posts, and only ever
removes `needs-info` when that marker is present and the issue now conforms — so
it can never strip a `needs-info` that `triage` set for a different reason.
