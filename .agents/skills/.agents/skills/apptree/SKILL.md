---
name: apptree
description: Docker-based isolated environments for php-arazzo development
---

# Apptree Skill

Use this skill when working on features or GitHub issues in php-arazzo.

## When to use

- Starting work on a new feature
- Working on a GitHub issue
- Need isolated testing environment
- Multiple branches in parallel

## Workflow

1. Create apptree:
   ```bash
   scripts/apptree create <branch-name>
   ```

2. Work inside container:
   ```bash
   scripts/apptree shell <branch-name>
   ```

3. Run tests:
   ```bash
   scripts/apptree test <branch-name>
   ```

4. Cleanup when done:
   ```bash
   scripts/apptree down <branch-name>
   ```

## Commands

| Command | Description |
|---------|-------------|
| `scripts/apptree create <branch>` | Create worktree + start Docker |
| `scripts/apptree shell <branch>` | Open shell in container |
| `scripts/apptree test <branch>` | Run Pest tests |
| `scripts/apptree down <branch>` | Stop containers + remove worktree |
| `scripts/apptree list` | List active instances |

## Tips

- Each apptree gets its own isolated Docker containers
- Files sync instantly via bind mounts
- Redis is available for cache/queue testing
- SQLite is used for database (no extra container needed)
- Multiple apptrees can run simultaneously
