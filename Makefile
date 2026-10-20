.PHONY: help test test-coverage test-mutate format analyse analyse-baseline phpmd phpmd-report phpmd-baseline pdepend phpdoc ci-all ci-test ci-phpstan ci-format hooks-install verify docs insights quality-gates detect-fake audit-boundaries hume-audit scaffold-test test-scripts verify-falsification falsify coverage coverage-core coverage-laravel coverage-query coverage-hotspots coverage-dashboard severity-audit property-audit socratic-fuzz demon-sim verify-falsification-v2 report report-json report-all

# Every package with a src/ directory, in one place. The four phpmd targets and
# the CI job all scan this set; tests/scripts/PhpmdGateWiringTest.php holds them
# to it, so a package split out of core cannot sit outside the gate unnoticed.
PHPMD_PATHS = packages/core/src packages/contracts/src packages/document/src packages/expression/src packages/evaluation/src packages/sources/src packages/runtime/src packages/engine/src packages/events/src packages/runner/src packages/cli/src packages/laravel/src packages/protocol-http/src packages/protocol-rpc/src packages/protocol-soap/src

# pdepend takes the same paths comma-joined, in one argument.
empty :=
space := $(empty) $(empty)
comma := ,
PHPMD_PATHS_COMMA = $(subst $(space),$(comma),$(PHPMD_PATHS))

docs: ## Regenerate architecture diagrams into docs/generated/
	php scripts/generate-docs.php

quality-gates: ## Measure quality gates into storage/quality-gates.json (ARGS="--with-mutations" adds mutation testing)
	php scripts/quality-gates.php $(ARGS)

help: ## Show this help
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

test: ## Run Pest tests
	composer run test

test-coverage: ## Run Pest tests with coverage
	cd packages/core && vendor/bin/pest --coverage
	cd packages/laravel && vendor/bin/pest --coverage

test-mutate: ## Run Pest mutation testing
	cd packages/core && vendor/bin/pest --mutate --covered-only
	cd packages/laravel && vendor/bin/pest --mutate --covered-only

format: ## Format code using Laravel Pint
	vendor/bin/pint

analyse: ## Run PHPStan static analysis
	composer run analyse

analyse-baseline: ## Regenerate every PHPStan baseline (one per package that has one)
	@# Each package config inlines `includes: - phpstan-baseline.neon`; PHPStan can
	@# regenerate a baseline in place, but only once the file exists — a package that
	@# should have one needs it created first.
	@# Always pass the output path
	@# (`--generate-baseline=packages/<pkg>/phpstan-baseline.neon`): PHPStan writes the
	@# file next to the *cwd* and spells its `path:` entries relative to it, so a bare
	@# `--generate-baseline` from the repo root drops a baseline there full of
	@# `packages/<pkg>/src/...` paths. Pointed at the package, both stay package-relative,
	@# which is what the committed baselines use.
	@# The package list comes from the baselines that exist, so a package added to the
	@# split is picked up rather than forgotten. `core` is not here at all: it has no
	@# baseline (empty src, and its config's cwd-relative `includes:` cannot resolve).
	@# Analysis runs from the repo root on the root binary, exactly like the
	@# `composer analyse-*` scripts — larastan needs the root as cwd to bootstrap.
	@# `--allow-empty-baseline` lets an errors-free package (laravel today) write an
	@# empty baseline; PHPStan spells that `ignoreErrors: []`, so rewrite it to the
	@# `parameters: {}` the repo commits, or every run would churn the file.
	@# And `--generate-baseline` freezes whatever it finds, so it is guarded (#203,
	@# docs/BASELINE-POLICY.md): the tree must be policy-clean before we start, and every
	@# baseline we rewrite is audited — one that now hides a correctness error is put
	@# back the way it was and the target fails instead of leaving it behind.
	@php scripts/check-baselines.php || { \
	  echo ""; \
	  echo "❌ This tree already hides a correctness error — nothing was regenerated."; \
	  echo "💡 Fix the code; baselines may not hide it. See docs/BASELINE-POLICY.md."; \
	  exit 1; \
	}
	@for baseline in packages/*/phpstan-baseline.neon; do \
	  pkg=$$(basename "$$(dirname "$$baseline")"); \
	  echo "==> $$pkg"; \
	  previous=$$(mktemp); \
	  cp "$$baseline" "$$previous"; \
	  if ! vendor/bin/phpstan analyse -c "packages/$$pkg/phpstan.neon.dist" \
	      --generate-baseline="$$baseline" --allow-empty-baseline --memory-limit=1G; then \
	    rm -f "$$previous"; \
	    echo ""; \
	    echo "❌ PHPStan could not regenerate $$baseline — it was left untouched."; \
	    exit 1; \
	  fi; \
	  if ! grep -q 'message:' "$$baseline"; then \
	    printf 'parameters: {}\n' > "$$baseline"; \
	  fi; \
	  if ! php scripts/check-baselines.php; then \
	    cp "$$previous" "$$baseline"; \
	    rm -f "$$previous"; \
	    echo ""; \
	    echo "❌ $$baseline now hides a correctness error — previous baseline restored."; \
	    echo "💡 Fix the code; baselines may not hide it. See docs/BASELINE-POLICY.md."; \
	    exit 1; \
	  fi; \
	  rm -f "$$previous"; \
	done

phpmd: ## Run PHP Mess Detector (fails on any violation not in the baseline)
	vendor/bin/phpmd analyze $(PHPMD_PATHS) --format ansi --ruleset phpmd.xml --baseline-file phpmd.baseline.xml --threads 1

phpmd-report: ## Generate PHPMD XML report to storage/phpmd-report.xml
	@mkdir -p storage
	vendor/bin/phpmd analyze $(PHPMD_PATHS) --format xml --ruleset phpmd.xml --reportfile-xml storage/phpmd-report.xml --threads 1

# Regenerating straight into phpmd.baseline.xml truncates it: PHPMD opens the
# file for writing before analysis finishes, and a PDepend parse failure aborts
# the process mid-write (101 entries -> 0, measured). So the new baseline is
# built beside the old one and swapped in only after a clean run.
phpmd-baseline: ## Regenerate PHPMD baseline from the current violations (writes only on success)
	@tmp=$$(mktemp); \
	if vendor/bin/phpmd analyze $(PHPMD_PATHS) --ruleset phpmd.xml --generate-baseline --baseline-file "$$tmp" --threads 1; then \
		mv "$$tmp" phpmd.baseline.xml; \
	else \
		rm -f "$$tmp"; \
		echo "phpmd-baseline: generation failed; phpmd.baseline.xml left untouched"; \
		exit 1; \
	fi

pdepend: ## Run PHP-Depend structural metrics (outputs to storage/pdepend/)
	@mkdir -p storage/pdepend
	vendor/bin/pdepend \
		--summary-xml=storage/pdepend/summary.xml \
		--jdepend-chart=storage/pdepend/jdepend.svg \
		--overview-pyramid=storage/pdepend/pyramid.svg \
		$(PHPMD_PATHS_COMMA)

phpdoc: ## Generate API documentation into docs/generated/api/
	vendor/bin/phpdoc run

ci-all: ## Run all GitHub Actions locally using act
	act --container-architecture linux/amd64

ci-test: ## Run test GitHub Action locally using act
	act -j test --container-architecture linux/amd64

ci-phpstan: ## Run phpstan GitHub Action locally using act
	act -j phpstan --container-architecture linux/amd64

ci-format: ## Run code styling GitHub Action locally using act
	act -j php-code-styling --container-architecture linux/amd64

hooks-install: ## Point git at .githooks/ (one-time per clone)
	git config core.hooksPath .githooks
	@echo "hooks installed: pre-push will run pint --test + phpstan + pest before pushing main"

verify: ## Run the same gates the pre-push hook runs (docs, pint, analyse, baselines, tests)
	php scripts/generate-docs.php
	vendor/bin/pint --test
	composer run analyse
	composer run check-baselines
	composer run test

# Falsification testing (Popper/Hume/Socrates/Descartes) — see .agents/skills/falsification-testing/SKILL.md
detect-fake: ## Run Fake Test Detector (ARGS="--all --json" or path)
	@php .agents/skills/falsification-testing/scripts/detect-fake-tests.php $(or $(ARGS),--all)

audit-boundaries: ## Hume boundary checklist (ARGS="WorkflowEngine --json" or src path)
	@php .agents/skills/falsification-testing/scripts/audit-boundaries.php $(ARGS)

hume-audit: ## Mutation/Hume audit MSI >=80 (ARGS="--threshold 90 --dry-run --all")
	@bash .agents/skills/falsification-testing/scripts/hume-audit.sh $(or $(ARGS),--all --threshold 80)

scaffold-test: ## Scaffold falsification test (ARGS="core MyFeature 'claim'")
	@php .agents/skills/falsification-testing/scripts/scaffold-falsification-test.php $(ARGS)

test-scripts: ## Self-test falsification scripts (harness + Pest wrapper)
	@bash .agents/skills/falsification-testing/scripts/test-scripts.sh

verify-falsification: ## Full falsification gate: fake+dry+pint+analyse+pest+hume (ARGS="--quick --with-mutate --threshold 80")
	@bash .agents/skills/falsification-testing/scripts/verify-falsification.sh $(ARGS)

falsify: ## Alias for verify-falsification --quick
	@bash .agents/skills/falsification-testing/scripts/verify-falsification.sh --quick

# Coverage insights — Pest HTML (see .agents/skills/coverage-insights/SKILL.md)
coverage: ## Generate coverage reports for both packages (phpdbg)
	@bash .agents/skills/falsification-testing/scripts/generate-coverage.sh --all

coverage-core: ## Generate coverage for core only
	@bash .agents/skills/falsification-testing/scripts/generate-coverage.sh --core

coverage-laravel: ## Generate coverage for laravel only
	@bash .agents/skills/falsification-testing/scripts/generate-coverage.sh --laravel

coverage-query: ## Query coverage report (ARGS="--overview --json" or --file X --hotspots)
	@php .agents/skills/falsification-testing/scripts/query-coverage.php $(or $(ARGS),--overview)

coverage-hotspots: ## Show lowest-coverage hotspots (ARGS="--limit 10 --json")
	@php .agents/skills/falsification-testing/scripts/query-coverage.php --hotspots $(ARGS)

coverage-dashboard: ## Show dashboard/insufficient coverage (ARGS="--json --package all")
	@php .agents/skills/falsification-testing/scripts/query-coverage.php --dashboard $(ARGS)

# Falsification V2 — severity/grue/agon/demon (see .agents/skills/falsification-testing-v2/SKILL.md)
severity-audit: ## Lakatos severity (ARGS="--filter WorkflowEngine --json --threshold 0.7")
	@php .agents/skills/falsification-testing/scripts/severity-audit.php $(ARGS)

property-audit: ## Goodman grue + property gaps (ARGS="--json --package core")
	@php .agents/skills/falsification-testing/scripts/property-audit.php $(ARGS)

socratic-fuzz: ## Hegelian agon fuzz Arazzo YAML (ARGS="--iterations 50 --json")
	@php .agents/skills/falsification-testing/scripts/socratic-fuzz.php $(or $(ARGS),--iterations 10)

demon-sim: ## Cartesian demon sim (ARGS="--seeds 5 --json --filter X")
	@php .agents/skills/falsification-testing/scripts/demon-sim.php $(or $(ARGS),--seeds 3)

verify-falsification-v2: ## Full V1+V2 gate (severity+property+fuzz+demon)
	@bash -c 'make severity-audit && make property-audit && make socratic-fuzz && make demon-sim'

report: ## Full human report (all 12 scripts, V1+coverage+V2)
	@bash .agents/skills/falsification-testing/scripts/generate-report.sh

report-json: ## Full agent JSON report (all scripts, --json)
	@bash .agents/skills/falsification-testing/scripts/generate-report.sh --json

report-all: ## Both human + JSON to storage/report.json + storage/report.md
	@mkdir -p storage
	@bash .agents/skills/falsification-testing/scripts/generate-report.sh --json --out storage/report.json
	@bash .agents/skills/falsification-testing/scripts/generate-report.sh > storage/report.md
	@echo "reports: storage/report.json (agent) + storage/report.md (human)"

INSIGHTS := $(HOME)/Code/Me/software-development-dashboard/bin/insights

insights: ## Query code-quality insights (ARGS="command ..."; no ARGS = show options)
	@if [ -z "$(ARGS)" ]; then \
		$(INSIGHTS) --help; \
		echo; \
		echo "Examples:"; \
		echo "  make insights ARGS=\"overview --format table\""; \
		echo "  make insights ARGS=\"hotspots --limit 10\""; \
		echo "  make insights ARGS=\"violations --severity critical --module core\""; \
		echo "  make insights ARGS=\"history --metric avg_mi --since 2026-01-01\""; \
		echo "  make insights ARGS=\"scan\"            # refresh metrics after coding"; \
		echo "  make insights ARGS=\"schema\"           # full machine-readable contract for agents"; \
	else \
		$(INSIGHTS) $(ARGS); \
	fi

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
