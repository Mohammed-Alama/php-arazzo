.PHONY: help test test-coverage test-mutate format analyse ci-all ci-test ci-phpstan ci-format hooks-install verify

help: ## Show this help
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

test: ## Run Pest tests
	vendor/bin/pest

test-coverage: ## Run Pest tests with coverage
	vendor/bin/pest --coverage

test-mutate: ## Run Pest mutation testing
	vendor/bin/pest --mutate --covered-only

format: ## Format code using Laravel Pint
	vendor/bin/pint

analyse: ## Run PHPStan static analysis
	vendor/bin/phpstan analyse

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
	vendor/bin/phpstan analyse --no-progress --memory-limit=1G
	vendor/bin/pest --ci
