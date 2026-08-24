.PHONY: help test test-coverage test-mutate format analyse analyse-baseline ci-all ci-test ci-phpstan ci-format hooks-install verify docs

docs: ## Regenerate architecture diagrams into docs/generated/
	php scripts/generate-docs.php

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
