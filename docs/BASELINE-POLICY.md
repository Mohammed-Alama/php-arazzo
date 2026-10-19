# PHPStan Baseline Policy

> **A baseline may freeze *style*. It may never freeze *correctness*.**
>
> If PHPStan is telling you the code is wrong, fix the code. Suppressing the error is
> never the fix — it is how the bug survives the next refactor unnoticed.

## Where suppressions live

Only in `packages/*/phpstan-baseline.neon`.

An `ignoreErrors:` block in a PHPStan config (`phpstan.neon.dist`) is **never** allowed,
whatever it contains: config patterns are hand-written, they carry no `identifier:`, and
so they slip past every rule below. An empty placeholder (`ignoreErrors: []`) is fine, as
are `paths:`, `excludePaths:`, `scanDirectories:` and `includes:` — those are config, not
suppressions.

## What a baseline entry may contain

An entry is legal only if it is one of these two:

| # | Shape | Why it is acceptable |
|---|---|---|
| 1 | **No `identifier:` at all** | This repo's own rules (cyclomatic complexity, parameter count, method count, method-name length, …) emit no identifier. Those findings are style signal, not defects — and they are the *only* thing worth freezing. |
| 2 | **`identifier:` matches `missingType.*`** | An untyped collection is a documentation gap, not a crash. Prefer annotating the code; baseline only when that is out of reach. |

Everything else — `method.notFound`, `method.nonObject`, `function.alreadyNarrowedType`,
`return.type`, … — is a real defect that has to be fixed in the code.

Run `grep -rn 'identifier:' packages/*/phpstan-baseline.neon` to see the exceptions that
qualify today.

```neon
# ✅ legal — one of our own rules, no identifier emitted
-
    message: '#^Method toMarkdown has too high cyclomatic complexity \(17\)\. Maximum allowed is 10\.$#'
    count: 1
    path: src/Renderer/Renderer.php

# ✅ legal — documentation gap, not a defect
-
    message: '#^Method Alama\\Arazzo\\Contracts\\Spec\\GraphQlOperation::__construct\(\) has parameter \$extensions with no value type specified in iterable type iterable\.$#'
    identifier: missingType.iterableValue
    count: 1
    path: src/Spec/GraphQlOperation.php

# ❌ illegal — a correctness error frozen in a baseline
-
    message: '#^Call to an undefined method Foo\:\:bar\(\)\.$#'
    identifier: method.notFound
    count: 1
    path: src/Foo.php
```

Every package config sets `reportUnmatchedIgnoredErrors: true`, so an entry that no longer
matches anything is reported by PHPStan itself and cannot linger.

## The gate

[`scripts/check-baselines.php`](../scripts/check-baselines.php) enforces both rules.

```bash
composer run check-baselines                        # human-readable
php scripts/check-baselines.php --json              # machine-readable
php scripts/check-baselines.php --root=path/to/tree # check some other tree
```

Exit codes: `0` clean, `1` at least one disallowed suppression, `2` usage error.

It runs on every path that can merge code — the `pre-commit` hook, the PHPStan CI job,
`make verify`, and `scripts/quality-gates.php` (the *Baseline Policy · correctness* row of
[`docs/generated/quality-gates.md`](generated/quality-gates.md)). The wiring itself is
pinned by
[`tests/scripts/BaselinePolicyWiringTest.php`](../tests/scripts/BaselinePolicyWiringTest.php),
so a gate cannot quietly stop running the checker.

## Regenerating a baseline

```bash
make analyse-baseline
```

It walks every package that has a `phpstan-baseline.neon` — the list is derived from the
files, so a package added to the split joins automatically. `--generate-baseline` is happy
to freeze whatever is broken, so the target wraps it:

1. the gate runs **before** anything is rewritten — a tree that is already hiding a
   correctness error is refused there and then;
2. every baseline it rewrites is audited **afterwards** — if the new baseline would hide a
   correctness error, the previous baseline is restored and the target fails.

Running `vendor/bin/phpstan analyse ... --generate-baseline` by hand has no such guard.
If in doubt, run `composer run check-baselines` afterwards and read the diff.

## Why this exists

The checker and this policy came out of epic [#197](https://github.com/Mohammed-Alama/php-arazzo/issues/197)
and its story [#198](https://github.com/Mohammed-Alama/php-arazzo/issues/198), which swept
every PHPStan baseline in the monorepo. The sweep found real defects frozen in
baselines — dead code paths, now-unreachable branches, methods called on values that could
never be objects — each one invisible precisely because a suppression had been written
around it instead of a fix.

The lesson is the policy: a suppression is a decision to stop seeing something, so it is
allowed for *taste* and refused for *truth*.
