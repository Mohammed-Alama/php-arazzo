.PHONY: help test test-coverage test-mutate format analyse analyse-baseline ci-all ci-test ci-phpstan ci-format hooks-install verify docs insights quality-gates detect-fake audit-boundaries hume-audit scaffold-test test-scripts verify-falsification falsify coverage coverage-core coverage-laravel coverage-query coverage-hotspots coverage-dashboard severity-audit property-audit socratic-fuzz demon-sim verify-falsification-v2 report report-json report-all

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

analyse-baseline: ## Regenerate PHPStan baselines
	cd packages/core && vendor/bin/phpstan analyse --generate-baseline --memory-limit=1G
	cd packages/laravel && vendor/bin/phpstan analyse --generate-baseline --memory-limit=1G

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

verify: ## Run the same gates the pre-push hook runs
	vendor/bin/pint --test
	composer run analyse
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
