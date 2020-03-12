##
## Makefile
##
include .env
export

RED=033[31m
GREEN=033[32m
YELLOW=033[33m
BLUE=033[34m
PURPLE=033[35m
CYAN=033[36m
GREY=033[37m
NC=033[0m

DOCKER-COMPOSE=docker-compose

DOCKER-EXEC=$(DOCKER-COMPOSE) exec

SH=$(DOCKER-EXEC) sh /bin/sh
CONSOLE=$(DOCKER-EXEC) sh bin/console --env=prod
CONSOLE-DEV=$(DOCKER-EXEC) sh bin/console --env=dev
COMPOSER=$(DOCKER-EXEC) sh composer
SERVICES=sql http http-worker-prod http-worker-dev http-worker-xdebug mail fpm fpm-xdebug sh sh-xdebug

.env:
	cp .env.dist .env

update-containers: ## Pull last images and launch
update-containers: .env pull start

.PHONY: start
start: ## Launch docker services
start: .env
	@echo "\${BLUE}- Lauching docker\${NC}"
	$(DOCKER-COMPOSE) up -d $(SERVICES)

.PHONY: start-mq
start-mq: ## Launch docker MQ worker service
start-mq: .env
	@echo "\${BLUE}- Lauching docker\${NC}"
	$(DOCKER-COMPOSE) up -d mq

.PHONY: start-ws
start-ws: ## Launch docker WS service
start-ws: .env
	@echo "\${BLUE}- Lauching docker\${NC}"
	$(DOCKER-COMPOSE) up -d ws

.PHONY: stop
stop: ## Stop docker services
stop: .env
	@echo "\${BLUE}- Stopping docker\${NC}"
	$(DOCKER-COMPOSE) stop

.PHONY: pull
pull: ## Pull latest images for docker services
pull: .env
	$(DOCKER-COMPOSE) pull

.PHONY: clean
clean: ## Clean project.
clean: .env start
	@echo "\${BLUE}- Cleaning project\${NC}"
	$(SH) -c "/usr/bin/env rm -Rf app/cache vendor composer.lock && git checkout composer.lock"

platform-update: ## Execute platform application update commands and init platform assets.
platform-update: .env start
	@echo "\${BLUE}- Executing platform application update\${NC}"
	$(CONSOLE) oro:platform:update --force --symlink -vvv --timeout=0

.PHONY: cache-clear
cache-clear: ## Clear cache
cache-clear:
	@echo "\${YELLOW}- Clearing PROD cache\${NC}"
	$(CONSOLE) cache:clear

.PHONY: cache-clear-dev
cache-clear-dev: ## Clear cache
cache-clear-dev:
	@echo "\${YELLOW}- Clearing DEV cache\${NC}"
	$(CONSOLE-DEV) cache:clear

.PHONY: cache-warmup
cache-warmup: ## Warmup cache
cache-warmup:
	@echo "\${YELLOW}- Warming up PROD cache\${NC}"
	$(CONSOLE) cache:warmup

.PHONY: cache-warmup-dev
cache-warmup-dev: ## Warmup cache
cache-warmup-dev:
	@echo "\${YELLOW}- Warming up DEV cache\${NC}"
	$(CONSOLE-DEV) cache:warmup

.PHONY: install
install: ## Install Helpdesk
install: vendor start
	@echo "\${YELLOW}- Installing Helpdesk\${NC}"
	$(CONSOLE) diamante:install \
		--application-url=$(OROCOMMERCE_APPLICATION_URL) \
		--organization-name=$(OROCOMMERCE_ORGANIZATION_NAME) \
		--user-name=$(OROCOMMERCE_ADMIN_USERNAME) \
		--user-email=$(OROCOMMERCE_ADMIN_EMAIL) \
		--user-firstname=$(OROCOMMERCE_ADMIN_FIRSTNAME) \
		--user-lastname=$(OROCOMMERCE_ADMIN_LASTNAME) \
		--user-password=$(OROCOMMERCE_ADMIN_PASSWORD) \
		--language=$(OROCOMMERCE_LANGUAGE) \
		--formatting-code=$(OROCOMMERCE_LANGUAGE) \
		--symlink \
		--timeout=0

.PHONY: uninstall
uninstall: ## Uninstall Helpdesk
uninstall: start
	@echo "\${YELLOW}- Uninstalling Helpdesk\${NC}"
	$(SH) -c "rm -rf var/cache/*"
	$(SH) -c "bin/uninstall"

.PHONY: reinstall
reinstall: ## Reinstall Helpdesk
reinstall: vendor uninstall start
	@echo "\${YELLOW}- Reinstalling Helpdesk\${NC}"
	$(CONSOLE) diamante:install \
		--application-url=$(OROCOMMERCE_APPLICATION_URL) \
		--organization-name=$(OROCOMMERCE_ORGANIZATION_NAME) \
		--user-name=$(OROCOMMERCE_ADMIN_USERNAME) \
		--user-email=$(OROCOMMERCE_ADMIN_EMAIL) \
		--user-firstname=$(OROCOMMERCE_ADMIN_FIRSTNAME) \
		--user-lastname=$(OROCOMMERCE_ADMIN_LASTNAME) \
		--user-password=$(OROCOMMERCE_ADMIN_PASSWORD) \
		--language=$(OROCOMMERCE_LANGUAGE) \
		--formatting-code=$(OROCOMMERCE_LANGUAGE) \
		--symlink \
		--timeout=0 \
		--drop-database

vendor: composer.json composer.lock start
	@echo "\${BLUE}- Installing composer dependencies\${NC}"
	$(COMPOSER) install

.DEFAULT_GOAL := help

.PHONY: help
help: ## Show this help
help:
	@echo "\n\${BLUE}usage: make \${GREEN}command [...command]\${NC}"
	@grep -E '(^[a-zA-Z_-]+:.*?##.*$$)|(^##)' Makefile | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'
