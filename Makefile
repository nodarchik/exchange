# Cryptocurrency Exchange API - Docker Management

.PHONY: help dev prod build up down logs shell migrate test clean

# Default target
help: ## Show this help message
	@echo "Cryptocurrency Exchange API - Docker Commands"
	@echo "=============================================="
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

# Development commands
dev: ## Start development environment
	docker compose up -d
	@echo "Development environment started at http://localhost:8080"
	@echo "API endpoints:"
	@echo "  - GET  /api/rates/last-24h?pair=EUR/BTC"
	@echo "  - GET  /api/rates/day?pair=EUR/BTC&date=2024-01-15"
	@echo "  - GET  /api/rates/health"

dev-build: ## Build and start development environment
	docker compose build --no-cache
	docker compose up -d

# Production commands
prod: ## Start production environment
	docker compose -f compose.prod.yaml up -d
	@echo "Production environment started"

prod-build: ## Build and start production environment
	docker compose -f compose.prod.yaml build --no-cache
	docker compose -f compose.prod.yaml up -d

# Common operations
build: ## Build Docker images
	docker compose build

up: ## Start services (development)
	docker compose up -d

down: ## Stop all services
	docker compose down
	docker compose -f compose.prod.yaml down 2>/dev/null || true

logs: ## Show logs from all services
	docker compose logs -f

logs-app: ## Show application logs
	docker compose logs -f app

logs-nginx: ## Show nginx logs
	docker compose logs -f nginx

logs-db: ## Show database logs
	docker compose logs -f database

# Database operations
migrate: ## Run database migrations
	docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction

migrate-dev: ## Generate and run new migration
	docker compose exec app php bin/console doctrine:migrations:diff
	docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction

db-reset: ## Reset database (development only)
	docker compose exec app php bin/console doctrine:database:drop --force --if-exists
	docker compose exec app php bin/console doctrine:database:create
	docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction

# Application commands
fetch-rates: ## Manually fetch cryptocurrency rates
	docker compose exec app php bin/console app:fetch-rates

shell: ## Access application container shell
	docker compose exec app bash

shell-db: ## Access database shell
	docker compose exec database mysql -u app -p crypto_exchange

# Testing and development
test: ## Run tests
	docker compose exec app php bin/phpunit

composer-install: ## Install composer dependencies
	docker compose exec app composer install

composer-update: ## Update composer dependencies
	docker compose exec app composer update

cache-clear: ## Clear application cache
	docker compose exec app php bin/console cache:clear

# Monitoring and maintenance
stats: ## Show container resource usage
	docker stats

ps: ## Show running containers
	docker compose ps

# Cleanup commands
clean: ## Clean up Docker resources
	docker compose down -v
	docker system prune -f
	docker volume prune -f

clean-all: ## Clean up everything including images
	docker compose down -v --rmi all
	docker system prune -af
	docker volume prune -f

# Backup and restore
backup-db: ## Backup database
	docker compose exec database mysqldump -u app -p crypto_exchange > backup_$(shell date +%Y%m%d_%H%M%S).sql

restore-db: ## Restore database (specify file with FILE=backup.sql)
	@test -n "$(FILE)" || (echo "Specify backup file with FILE=backup.sql" && exit 1)
	docker compose exec -T database mysql -u app -p crypto_exchange < $(FILE)

# SSL/Production setup
ssl-cert: ## Generate SSL certificate (production)
	docker compose -f compose.prod.yaml run --rm traefik \
		sh -c "apk add --no-cache openssl && openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
		-keyout /letsencrypt/privkey.pem -out /letsencrypt/fullchain.pem"

# Environment setup
setup-env: ## Setup environment file
	@if [ ! -f .env.local ]; then \
		echo "Creating .env.local file..."; \
		echo "APP_ENV=dev" > .env.local; \
		echo "APP_SECRET=$$(openssl rand -hex 32)" >> .env.local; \
		echo "DATABASE_URL=mysql://app:password@database:3306/crypto_exchange?serverVersion=8.0&charset=utf8mb4" >> .env.local; \
		echo "Environment file created: .env.local"; \
	else \
		echo ".env.local already exists"; \
	fi

# Quick start
quick-start: setup-env dev-build migrate fetch-rates ## Complete setup for new installation
	@echo ""
	@echo "Cryptocurrency Exchange API is ready!"
	@echo "Dashboard: http://localhost:8080"
	@echo "Health check: http://localhost:8080/api/rates/health"
	@echo "Test endpoint: http://localhost:8080/api/rates/last-24h?pair=EUR/BTC"
