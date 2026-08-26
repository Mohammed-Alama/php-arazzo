# apptree Plan

Docker-based isolated environments for php-arazzo, integrated with git worktrees.

## Files to create/modify

| # | File | Purpose |
|---|------|---------|
| 1 | `Dockerfile` | Alpine PHP 8.4 image (optimized) |
| 2 | `.dockerignore` | Exclude files from build context |
| 3 | `docker-compose.yml` | php + redis services |
| 4 | `scripts/apptree` | CLI: create/shell/test/down/list |
| 5 | `Makefile` | Add apptree targets |
| 6 | `.opencode/worktree.jsonc` | Hook into existing worktree plugin |
| 7 | `CLAUDE.md` | Instructions for Claude Code agents |
| 8 | `.agents/skills/apptree/SKILL.md` | Reusable agent skill |

---

## 1. Dockerfile (Optimized Alpine)

```dockerfile
# ============================================================================
# Stage 1: Build PHP extensions
# ============================================================================
FROM php:8.4-cli-alpine AS builder

RUN apk add --no-cache \
    sqlite-dev \
    $PHPIZE_DEPS \
    && docker-php-ext-install pdo_sqlite mbstring \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/pear

# ============================================================================
# Stage 2: Final minimal image
# ============================================================================
FROM php:8.4-cli-alpine

# Copy compiled extensions from builder
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Install runtime dependencies only (no dev headers)
RUN apk add --no-cache \
    git \
    nodejs \
    npm \
    composer \
    sqlite-libs \
    && rm -rf /var/cache/apk/* /tmp/* /usr/share/doc /usr/share/man

# Install global testing tools (cached layer)
RUN composer global require pestphp/pest phpstan/phpstan laravel/pint \
    && rm -rf /root/.composer/cache

ENV PATH="$PATH:/root/.composer/vendor/bin"
WORKDIR /app
```

**Size estimate**: ~120-150MB (optimized Alpine)

**Optimizations applied:**
- Multi-stage build: compile extensions in builder, copy only binaries
- Runtime-only libs: `sqlite-libs` instead of `sqlite-dev`
- No dev headers: `$PHPIZE_DEPS` only in builder stage
- Cache cleanup: rm -rf cache, docs, man pages
- Composer cache: removed after global install
- Single layer for apt: combined RUN commands

---

## 2. .dockerignore

Keep build context small by excluding:

```dockerignore
# Version control
.git
.gitignore
.gitattributes

# IDE
.idea
.vscode
*.swp

# Dependencies (mounted via volume)
vendor
node_modules

# Test artifacts
.phpunit.cache
test-results
playwright-report

# Build artifacts
build
storage
.cache
.scratch

# Documentation
docs
docs_ignored
*.md
!README.md

# Docker (avoid recursive builds)
Dockerfile
docker-compose.yml
.dockerignore

# OS files
.DS_Store
Thumbs.db
```

---

## 3. docker-compose.yml

```yaml
services:
  php:
    build: .
    volumes:
      - .:/app
    working_dir: /app
    depends_on:
      - redis
    environment:
      - APP_ENV=testing
      - DB_CONNECTION=sqlite
      - DB_DATABASE=:memory:
    stdin_open: true
    tty: true

  redis:
    image: redis:7-alpine
    ports:
      - "${REDIS_PORT:-6379}"
```

**Notes**:
- Bind mount syncs worktree directory instantly
- `stdin_open` + `tty` keep container alive for shell access
- Redis port auto-assigned via env var

---

## 4. scripts/apptree

```bash
#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
WORKTREE_DIR="$PROJECT_ROOT/.worktrees/feat"

usage() {
    cat <<EOF
Usage: $(basename "$0") <command> [args]

Commands:
  create <branch>   Create worktree + start Docker containers
  shell <branch>    Open shell in running container
  test <branch>     Run Pest tests in container
  down <branch>     Stop and remove containers
  list              List all active apptree instances

Examples:
  $(basename "$0") create my-feature
  $(basename "$0") shell my-feature
  $(basename "$0") test my-feature
  $(basename "$0") down my-feature
  $(basename "$0") list
EOF
}

cmd_create() {
    local branch="$1"
    local worktree_path="$WORKTREE_DIR/$branch"

    echo "==> Creating worktree: $branch"

    # Create git worktree
    if [ ! -d "$worktree_path" ]; then
        mkdir -p "$WORKTREE_DIR"
        git worktree add -b "$branch" "$worktree_path" HEAD
        echo "    Worktree created at $worktree_path"
    else
        echo "    Worktree already exists at $worktree_path"
    fi

    # Copy docker files to worktree if not present
    for f in Dockerfile docker-compose.yml; do
        if [ ! -f "$worktree_path/$f" ]; then
            cp "$PROJECT_ROOT/$f" "$worktree_path/$f"
        fi
    done

    # Start Docker containers
    echo "==> Starting Docker containers..."
    cd "$worktree_path"
    docker compose up -d --build

    # Install dependencies if needed
    if [ ! -d "$worktree_path/vendor" ]; then
        echo "==> Installing composer dependencies..."
        docker compose exec php composer install
    fi

    echo ""
    echo "==> apptree ready!"
    echo "    Worktree: $worktree_path"
    echo "    Shell:    $(basename "$0") shell $branch"
    echo "    Tests:    $(basename "$0") test $branch"
}

cmd_shell() {
    local branch="$1"
    local worktree_path="$WORKTREE_DIR/$branch"

    if [ ! -d "$worktree_path" ]; then
        echo "Error: No worktree found for branch '$branch'"
        echo "Run: $(basename "$0") create $branch"
        exit 1
    fi

    cd "$worktree_path"
    docker compose exec php sh
}

cmd_test() {
    local branch="$1"
    local worktree_path="$WORKTREE_DIR/$branch"

    if [ ! -d "$worktree_path" ]; then
        echo "Error: No worktree found for branch '$branch'"
        exit 1
    fi

    cd "$worktree_path"
    docker compose exec php vendor/bin/pest
}

cmd_down() {
    local branch="$1"
    local worktree_path="$WORKTREE_DIR/$branch"

    if [ ! -d "$worktree_path" ]; then
        echo "Error: No worktree found for branch '$branch'"
        exit 1
    fi

    echo "==> Stopping Docker containers..."
    cd "$worktree_path"
    docker compose down -v

    echo "==> Removing worktree..."
    cd "$PROJECT_ROOT"
    git worktree remove --force "$worktree_path"

    echo "==> apptree '$branch' removed"
}

cmd_list() {
    echo "==> Active apptree instances:"
    echo ""

    if [ ! -d "$WORKTREE_DIR" ] || [ -z "$(ls -A "$WORKTREE_DIR" 2>/dev/null)" ]; then
        echo "    (none)"
        return
    fi

    for dir in "$WORKTREE_DIR"/*/; do
        local branch
        branch=$(basename "$dir")
        local status="stopped"

        cd "$dir"
        if docker compose ps --status running 2>/dev/null | grep -q "php"; then
            status="running"
        fi
        cd "$PROJECT_ROOT"

        printf "    %-30s [%s]\n" "$branch" "$status"
    done
}

# Main
if [ $# -lt 1 ]; then
    usage
    exit 1
fi

command="$1"
shift

case "$command" in
    create)
        [ $# -lt 1 ] && { echo "Error: branch name required"; exit 1; }
        cmd_create "$1"
        ;;
    shell)
        [ $# -lt 1 ] && { echo "Error: branch name required"; exit 1; }
        cmd_shell "$1"
        ;;
    test)
        [ $# -lt 1 ] && { echo "Error: branch name required"; exit 1; }
        cmd_test "$1"
        ;;
    down)
        [ $# -lt 1 ] && { echo "Error: branch name required"; exit 1; }
        cmd_down "$1"
        ;;
    list)
        cmd_list
        ;;
    *)
        echo "Error: Unknown command '$command'"
        usage
        exit 1
        ;;
esac
```

---

## 5. Makefile additions

Add to end of Makefile:

```makefile
# Apptree - Docker-based isolated environments
APPTREE_SCRIPT = scripts/apptree

apptree-create: ## Create apptree for branch (ARGS="branch-name")
	@if [ -z "$(ARGS)" ]; then echo "Usage: make apptree-create ARGS=branch-name"; exit 1; fi
	$(APPTREE_SCRIPT) create $(ARGS)

apptree-shell: ## Open shell in apptree (ARGS="branch-name")
	@if [ -z "$(ARGS)" ]; then echo "Usage: make apptree-shell ARGS=branch-name"; exit 1; fi
	$(APPTREE_SCRIPT) shell $(ARGS)

apptree-test: ## Run tests in apptree (ARGS="branch-name")
	@if [ -z "$(ARGS)" ]; then echo "Usage: make apptree-test ARGS=branch-name"; exit 1; fi
	$(APPTREE_SCRIPT) test $(ARGS)

apptree-down: ## Stop apptree (ARGS="branch-name")
	@if [ -z "$(ARGS)" ]; then echo "Usage: make apptree-down ARGS=branch-name"; exit 1; fi
	$(APPTREE_SCRIPT) down $(ARGS)

apptree-list: ## List all apptree instances
	$(APPTREE_SCRIPT) list
```

---

## 6. .opencode/worktree.jsonc

This hooks into the existing OpenCode worktree plugin to auto-create Docker containers:

```jsonc
{
  "$schema": "https://registry.kdco.dev/schemas/worktree.json",

  "sync": {
    // Copy Docker files to new worktrees
    "copyFiles": [
      "Dockerfile",
      "docker-compose.yml"
    ],

    // Symlink vendor to save disk space
    "symlinkDirs": [
      "vendor",
      "node_modules"
    ]
  },

  "hooks": {
    // Start Docker containers after worktree creation
    "postCreate": [
      "docker compose up -d --build",
      "docker compose exec php composer install"
    ],

    // Stop Docker containers before worktree deletion
    "preDelete": [
      "docker compose down -v"
    ]
  }
}
```

---

## 7. CLAUDE.md

Instructions for Claude Code agents:

```markdown
# php-arazzo

PHP monorepo for alama/arazzo-core + alama/laravel-arazzo.

## Working on features

When working on any feature or GitHub issue, use the apptree setup:

1. Create isolated environment: `scripts/apptree create <branch-name>`
2. Work inside the container: `scripts/apptree shell <branch-name>`
3. Run tests: `scripts/apptree test <branch-name>`
4. When done: `scripts/apptree down <branch-name>`

## Available commands

- `scripts/apptree create <branch>` - Create worktree + start Docker
- `scripts/apptree shell <branch>` - Open shell in container
- `scripts/apptree test <branch>` - Run Pest tests
- `scripts/apptree down <branch>` - Stop containers + remove worktree
- `scripts/apptree list` - List active instances

## Testing

```bash
# Run all tests
composer run test

# Run core tests only
composer run test-core

# Run laravel tests only
composer run test-laravel

# Run with coverage
make test-coverage
```

## Code quality

```bash
# Format code
composer run format

# Static analysis
composer run analyse

# Full verification
make verify
```

## Project structure

- `packages/core/` - Arazzo core library
- `packages/laravel/` - Laravel integration package
- `tests/` - Test suites
- `scripts/` - Development scripts
```

---

## 8. .agents/skills/apptree/SKILL.md

Reusable agent skill:

```markdown
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
```

---

## Execution order

1. Create `Dockerfile` (optimized multi-stage)
2. Create `.dockerignore`
3. Create `docker-compose.yml`
4. Create `scripts/apptree` (make executable)
5. Update `Makefile`
6. Create `.opencode/worktree.jsonc`
7. Create `CLAUDE.md`
8. Create `.agents/skills/apptree/SKILL.md`

## Testing the setup

After implementation:

```bash
# Test create
scripts/apptree create test-branch

# Test shell
scripts/apptree shell test-branch

# Test list
scripts/apptree list

# Test down
scripts/apptree down test-branch
```
