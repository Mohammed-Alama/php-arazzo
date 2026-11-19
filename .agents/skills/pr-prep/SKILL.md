---
name: pr-prep
description: Prepare a PR from the current branch — run gates, draft title and body from commits. Use when opening a pull request or asking to prep/draft a PR.
---

# Prep Pull Request

One command from feature branch to reviewable draft. The script guards against running on `main`, warns about uncommitted changes, runs the three gates, and drafts the PR body from real commit subjects plus a diff stat.

## Run

```bash
bash .agents/skills/pr-prep/scripts/pr-prep.sh [base] [--create] [--no-gates]
```

- `base` defaults to `origin/main`; it is fetched first so the merge-base is current.
- Body lands in `.scratch/pr-body.md` and prints to stdout — **read it before creating**; commit subjects are bullet seeds, rewrite them into sentences when they read like a log.
- `--create` opens a **draft** PR via `gh`, refusing when any gate failed.
- `--no-gates` skips pint/phpstan/pest for WIP branches — never on a branch someone else will review.

Completion criterion: gates pass, body reads as prose (not raw commit spam), draft PR open with the right base.
