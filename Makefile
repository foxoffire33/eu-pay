# ╔══════════════════════════════════════════════════════════╗
# ║  EU Pay — Makefile                                      ║
# ╚══════════════════════════════════════════════════════════╝

.PHONY: help certs up down restart logs test migrate shell db-shell lint

COMPOSE = docker compose
PHP     = $(COMPOSE) exec php
CONSOLE = $(PHP) bin/console

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

# ── Certificates ─────────────────────────────────────────

certs: ## Generate HTTPS certificates with mkcert
	@echo "🔐 Generating mkcert certificates..."
	@mkdir -p docker/certs
	@mkcert -install 2>/dev/null || true
	@mkcert \
		-cert-file docker/certs/eupay.pem \
		-key-file docker/certs/eupay-key.pem \
		eupay.localhost \
		api.eupay.localhost \
		localhost \
		127.0.0.1 \
		::1
	@echo "✅ Certificates generated in docker/certs/"
	@echo "   Add to /etc/hosts:"
	@echo "   127.0.0.1  eupay.localhost api.eupay.localhost"

# ── Docker ───────────────────────────────────────────────

build: ## Build Docker images
	$(COMPOSE) build --no-cache

up: ## Start all services
	$(COMPOSE) up -d
	@echo ""
	@echo "🚀 EU Pay running:"
	@echo "   https://eupay.localhost"
	@echo "   https://api.eupay.localhost/health"
	@echo ""

down: ## Stop all services
	$(COMPOSE) down

restart: ## Restart all services
	$(COMPOSE) restart

logs: ## Tail logs (all services)
	$(COMPOSE) logs -f

logs-php: ## Tail PHP logs only
	$(COMPOSE) logs -f php

logs-nginx: ## Tail Nginx logs only
	$(COMPOSE) logs -f nginx

ps: ## Show running containers
	$(COMPOSE) ps

# ── Backend ──────────────────────────────────────────────

install: ## Composer install
	$(PHP) composer install

shell: ## Open bash shell in PHP container
	$(PHP) sh

migrate: ## Run Doctrine migrations
	$(CONSOLE) doctrine:migrations:migrate --no-interaction

jwt-keys: ## Generate JWT RS256 keypair
	$(PHP) sh -c 'mkdir -p config/jwt && openssl genpkey -algorithm RSA -out config/jwt/private.pem -pkeyopt rsa_keygen_bits:4096 -aes256 -pass pass:$${JWT_PASSPHRASE} && openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem -passin pass:$${JWT_PASSPHRASE}'
	@echo "✅ JWT keys generated in backend/config/jwt/"

test: ## Run PHPUnit tests
	$(PHP) vendor/bin/phpunit --testdox

test-coverage: ## Run PHPUnit with coverage
	$(PHP) vendor/bin/phpunit --coverage-text

lint: ## Run Symfony linters
	$(CONSOLE) lint:yaml config/
	$(CONSOLE) lint:container

cache-clear: ## Clear Symfony cache
	$(CONSOLE) cache:clear

# ── Database ─────────────────────────────────────────────

db-shell: ## Open PostgreSQL shell
	$(COMPOSE) exec postgres psql -U eupay

db-reset: ## Drop + recreate database
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(CONSOLE) doctrine:database:create
	$(CONSOLE) doctrine:migrations:migrate --no-interaction

# ── First-time setup ────────────────────────────────────

setup: certs build up install jwt-keys migrate ## Full first-time setup
	@echo ""
	@echo "═══════════════════════════════════════════"
	@echo "  ✅ EU Pay is ready!"
	@echo ""
	@echo "  🌐 https://eupay.localhost"
	@echo "  📋 https://api.eupay.localhost/health"
	@echo "  🧪 make test"
	@echo "═══════════════════════════════════════════"

# ── Landing Page ───────────────────────────────────────

landing-install: ## Install landing page dependencies
	cd landing-page && npm ci

landing-dev: ## Start landing page dev server (Vite HMR)
	cd landing-page && npm run dev

landing-build: ## Build landing page for production
	cd landing-page && npm run build

# ── Cleanup ──────────────────────────────────────────────

clean: down ## Stop containers + remove volumes
	$(COMPOSE) down -v --remove-orphans
	rm -rf docker/certs/*.pem
