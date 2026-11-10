DC = docker compose
NO_TTY = $(if $(CI),-T)
EXEC = $(DC) exec $(NO_TTY)
RUN = $(DC) run $(NO_TTY) --rm
PHP = $(EXEC) php
CONSOLE = $(PHP) php bin/console
PLAYWRIGHT_ARGS ?=
TEST_TOKEN ?=
PHPUNIT_ARGS ?=
PHP_TEST = $(EXEC) -e TEST_TOKEN=$(TEST_TOKEN) php

IMAGE ?= docker.io/injust/injust-web
TAG ?= $(shell git rev-parse --short=7 HEAD)
PLATFORM ?= linux/amd64
-include .deploy.env
DEPLOY_HOST ?=
DEPLOY_DIR ?=
REMOTE_DOCKER ?= docker
NEEDS_DEPLOY_TARGET = @test -n "$(DEPLOY_HOST)" -a -n "$(DEPLOY_DIR)" || { echo "Set DEPLOY_HOST and DEPLOY_DIR (e.g. in .deploy.env)"; exit 1; }
E2E_ASSETS_DIR ?= build-e2e
CI_BUNDLE ?= ci-build.tgz
export E2E_ASSETS_DIR
OUTPUT_SYNC = $(if $(filter output-sync,$(.FEATURES)),--output-sync=target)

EMPTY :=
SPACE := $(EMPTY) $(EMPTY)
COMMA := ,
ZERO_SHA = 0000000000000000000000000000000000000000
EMPTY_TREE = 4b825dc642cb6eb9a060e54bf8d69288fbee4904
GATE_BASE ?= HEAD
GATE_REF = $(or $(strip $(GATE_BASE)),HEAD)
GATE_SHA = $(if $(filter $(ZERO_SHA),$(GATE_REF)),$(EMPTY_TREE),$(GATE_REF))
GATE_TRACKED = $(shell git diff --no-renames --name-only --diff-filter=AM $(GATE_SHA))
GATE_UNTRACKED = $(shell git ls-files --others --exclude-standard)
GATE_PHP_TRACKED = $(filter src/%.php,$(GATE_TRACKED))
GATE_PHP_UNTRACKED = $(filter src/%.php,$(GATE_UNTRACKED))
GATE_PHP = $(sort $(GATE_PHP_TRACKED) $(GATE_PHP_UNTRACKED))
GATE_JS = $(sort $(filter-out %.test.js assets/vue/composables/% assets/vue/i18n/% %/router.js,$(filter assets/vue/%.js,$(GATE_TRACKED) $(GATE_UNTRACKED))))
INFECTION = $(EXEC) -e GIT_CONFIG_COUNT=1 -e GIT_CONFIG_KEY_0=diff.renames -e GIT_CONFIG_VALUE_0=false php vendor/bin/infection --coverage=var/coverage --skip-initial-tests --threads=4 --no-progress --show-mutations
VITEST_COVERAGE = npx vitest run --coverage.enabled --coverage.provider=v8 --coverage.reporter=json --coverage.reportsDirectory=var/coverage-js --coverage.include='assets/vue/**/*.js' --coverage.exclude='**/*.test.js' --coverage.exclude='assets/vue/composables/**' --coverage.exclude='assets/vue/i18n/**' --coverage.exclude='**/router.js'
BUILD = docker buildx build --platform $(PLATFORM) --target prod -t $(IMAGE):$(TAG) -t $(IMAGE):latest

.PHONY: up down build install assets assets-e2e db db-test db-test-threads fixtures migration test test-unit test-functional test-js deptrac depcruise phpstan cs cs-fix audit audit-php audit-js gate gate-base coverage-php coverage-js crap mutation crap-js mutation-js e2e e2e-run qa ci ci-up ci-build ci-bundle ci-unbundle ci-warmup ci-checks ci-e2e image push deploy deploy-files

up: ## Start the stack (site on http://localhost:8098, admin on /admin, Vite on :5178, mock mossyleaf accounts on :8099, Mailpit on :8028)
	mkdir -p var/share/tmp
	$(DC) up -d --wait php database node oidc mailpit
	@git config core.hooksPath .githooks

down:
	$(DC) down

build:
	$(DC) build

install:
	$(PHP) composer install
	$(RUN) --no-deps node npm install

assets:
	$(RUN) --no-deps node npm run build

assets-e2e:
	$(RUN) --no-deps -e ASSETS_DIR=$(E2E_ASSETS_DIR) node npm run build

db: ## Create and migrate the dev database
	$(CONSOLE) doctrine:database:create --if-not-exists
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration

fixtures: db ## Reset the dev database with demo content
	$(CONSOLE) doctrine:fixtures:load --no-interaction --purge-with-truncate
	$(CONSOLE) app:legal:install-defaults

db-test: ## Create and migrate the test database app_test$(TEST_TOKEN)
	$(PHP_TEST) php bin/console doctrine:database:create --if-not-exists --env=test
	$(PHP_TEST) php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=test

migration: ## Generate a migration from mapping changes
	$(CONSOLE) doctrine:migrations:diff --no-interaction

db-test-threads: ## Create and migrate app_test1 to app_test4 (one database per Infection thread)
	@for n in 1 2 3 4; do $(MAKE) --no-print-directory db-test TEST_TOKEN=$$n || exit 1; done

test: db-test ## PHPUnit (unit + functional; TEST_TOKEN=n uses its own database app_testn)
	$(PHP_TEST) php bin/phpunit

test-unit:
	$(PHP_TEST) php bin/phpunit --testsuite unit

test-functional: db-test
	$(PHP_TEST) php bin/phpunit --testsuite functional

test-js: ## Vitest (pure JS modules)
	$(RUN) --no-deps node npx vitest run

deptrac: ## Check onion layer dependencies
	$(PHP) vendor/bin/deptrac analyse --no-progress

cs: ## Coding standard check
	$(EXEC) -e PHP_CS_FIXER_IGNORE_ENV=1 php vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix:
	$(EXEC) -e PHP_CS_FIXER_IGNORE_ENV=1 php vendor/bin/php-cs-fixer fix

depcruise: ## Check front-end layer dependencies
	$(RUN) --no-deps node npx depcruise assets --config .dependency-cruiser.cjs

phpstan: ## Static analysis (level 10)
	$(PHP) vendor/bin/phpstan analyse --no-progress --memory-limit=1G

audit: audit-php audit-js ## Known vulnerabilities in the locked dependencies

audit-php:
	$(PHP) composer audit

audit-js:
	$(RUN) --no-deps node npm audit --omit=dev --audit-level=high

e2e: assets-e2e e2e-run ## Playwright against a dedicated APP_ENV=test container

e2e-run: ## Playwright against already built assets (E2E_ASSETS_DIR, default build-e2e)
	mkdir -p var/share/tmp
	$(DC) --profile e2e up -d --wait php-e2e
	$(EXEC) php-e2e php bin/console cache:clear --env=test
	$(EXEC) php-e2e php bin/console doctrine:database:drop --force --if-exists --env=test
	$(EXEC) php-e2e php bin/console doctrine:database:create --env=test
	$(EXEC) php-e2e php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=test
	$(EXEC) php-e2e php bin/console doctrine:fixtures:load --no-interaction --env=test
	$(EXEC) php-e2e php bin/console app:legal:install-defaults --env=test
	$(EXEC) php-e2e rm -rf var/share/e2e
	$(EXEC) php-e2e php bin/console cache:pool:clear cache.contact_limiter --env=test
	$(DC) --profile e2e run $(NO_TTY) --rm playwright sh -c "npm ci --no-audit --no-fund && ./node_modules/.bin/playwright test $(PLAYWRIGHT_ARGS)"

gate: cs phpstan deptrac depcruise coverage-php coverage-js crap mutation crap-js mutation-js ## Everything a commit must pass: style, layers, tests with coverage, CRAP and mutation on the changed code (GATE_BASE=<ref> to compare against)

gate-base:
	@git rev-parse --verify -q $(GATE_SHA)^{tree} >/dev/null || { echo "GATE_BASE $(GATE_REF) is unknown to git (shallow clone? fetch it)"; exit 1; }

coverage-php: db-test ## PHPUnit with pcov: var/coverage/{coverage-xml,junit.xml,crap4j.xml}
	rm -rf var/coverage
	$(PHP_TEST) php -d pcov.enabled=1 -d pcov.directory=/app/src bin/phpunit --coverage-xml=var/coverage/coverage-xml --log-junit=var/coverage/junit.xml --coverage-crap4j=var/coverage/crap4j.xml $(PHPUNIT_ARGS)

coverage-js: ## Vitest with v8 coverage of the pure modules: var/coverage-js/coverage-final.json
	$(RUN) --no-deps node $(VITEST_COVERAGE)

crap: coverage-php gate-base ## CRAP <= 10 on the changed src files
	$(if $(GATE_PHP),$(PHP) php bin/crap var/coverage/crap4j.xml 10 $(GATE_PHP),@echo "crap: no changed PHP in src/")

mutation: coverage-php gate-base ## Infection on the changed lines (minMsi 80, minCoveredMsi 95)
	$(if $(GATE_PHP),@$(MAKE) --no-print-directory db-test-threads)
	$(if $(GATE_PHP_TRACKED),$(INFECTION) --git-diff-lines --git-diff-base=$(GATE_SHA),@echo "mutation: no changed tracked PHP in src/")
	$(if $(GATE_PHP_UNTRACKED),$(INFECTION) --filter=$(subst $(SPACE),$(COMMA),$(GATE_PHP_UNTRACKED)),@echo "mutation: no new untracked PHP in src/")

crap-js: coverage-js gate-base ## CRAP <= 10 on the changed pure modules
	$(if $(GATE_JS),$(RUN) --no-deps node node bin/crap-js.mjs var/coverage-js/coverage-final.json 10 $(GATE_JS),@echo "crap-js: no changed pure module")

mutation-js: gate-base ## Stryker on the changed pure modules (break 80)
	$(if $(GATE_JS),$(RUN) --no-deps node npx stryker run --mutate $(subst $(SPACE),$(COMMA),$(GATE_JS)),@echo "mutation-js: no changed pure module")

qa: gate audit e2e

ci: ci-build ## Full suite from a fresh checkout (GitHub Actions runs ci-build once, then ci-checks and ci-e2e shards in parallel jobs)
	$(MAKE) -j4 $(OUTPUT_SYNC) ci-checks
	$(MAKE) ci-e2e

ci-up:
	mkdir -p var/share/tmp
	$(DC) up -d --wait php database oidc

ci-build: ci-up ## Install PHP and JS dependencies and build the production assets
	$(PHP) composer install --no-interaction --no-progress
	$(RUN) --no-deps node npm ci --no-audit --no-fund
	$(MAKE) assets

ci-bundle: ## Pack what ci-build produced for the CI test jobs (CI_BUNDLE)
	tar -czf $(CI_BUNDLE) vendor node_modules public/build $(wildcard public/bundles)

ci-unbundle: ## Unpack the ci-build output (CI_BUNDLE)
	tar -xzf $(CI_BUNDLE)

ci-warmup: ci-up ## Start the dev stack and warm its cache (PHPStan reads the dev container)
	$(CONSOLE) cache:warmup

ci-e2e: ## Playwright on the production build (PLAYWRIGHT_ARGS to pick a shard)
	$(MAKE) e2e-run E2E_ASSETS_DIR=build

ci-checks: gate audit

image: ## Build the production image locally (IMAGE, TAG, PLATFORM)
	$(BUILD) --load .

push: qa ## Run the full suite, then build and push the production image by hand (CI does it on every push to main; run docker login first)
	$(BUILD) --push .

deploy-files: ## Copy deploy/ (compose files, env template, README) to DEPLOY_HOST:DEPLOY_DIR
	$(NEEDS_DEPLOY_TARGET)
	ssh $(DEPLOY_HOST) 'mkdir -p $(DEPLOY_DIR)'
	scp deploy/compose.yaml deploy/compose.override.yaml deploy/.env.dist deploy/README.md $(DEPLOY_HOST):$(DEPLOY_DIR)/

deploy: ## Run IMAGE:TAG (published by CI) on DEPLOY_HOST: set TAG in its .env, pull, restart
	$(NEEDS_DEPLOY_TARGET)
	@curl -sf -o /dev/null https://hub.docker.com/v2/repositories/$(patsubst docker.io/%,%,$(IMAGE))/tags/$(TAG) || { echo "$(IMAGE):$(TAG) is not published: CI only publishes it once the full test suite passes (still running, or failed?)"; exit 1; }
	scp deploy/compose.yaml $(DEPLOY_HOST):$(DEPLOY_DIR)/
	ssh $(DEPLOY_HOST) 'set -e; cd $(DEPLOY_DIR); \
		sed -i "s|^IMAGE=.*|IMAGE=$(IMAGE)|; s|^TAG=.*|TAG=$(TAG)|" .env; \
		$(REMOTE_DOCKER) compose pull app; \
		$(REMOTE_DOCKER) compose up -d --remove-orphans'
