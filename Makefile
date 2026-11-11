# Быстрый запуск и проверки. Всё выполняется в контейнерах, кроме фронтенд-тестов (нужен Node 22 и Chrome).
COMPOSE := docker compose
DEV     := $(COMPOSE) --profile dev run --rm backend-dev

.DEFAULT_GOAL := help
.PHONY: help init up down restart logs ps test lint cs-fix phpstan check fixtures user frontend-install frontend-test e2e

help: ## Список команд
	@grep -hE '^[a-zA-Z0-9_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

init: ## Создать .env из .env.example со случайными паролями и JWT-секретом
	@test -f .env || { \
		cp .env.example .env; \
		pass=$$(openssl rand -hex 16); \
		sed -i "s|change-me|$$pass|g" .env; \
		sed -i "s|^JWT_SECRET=.*|JWT_SECRET=$$(openssl rand -base64 48 | tr -d '\n')|" .env; \
		echo ".env создан"; }

up: init ## Собрать и поднять стек (http://localhost:8080)
	$(COMPOSE) up -d --build
	@echo "Приложение: http://localhost:8080, Swagger UI: http://localhost:8080/api/docs"

down: ## Остановить стек
	$(COMPOSE) down

restart: down up ## Перезапустить стек

logs: ## Логи app и воркеров
	$(COMPOSE) logs -f app worker

ps: ## Состояние контейнеров
	$(COMPOSE) ps

test: ## PHPUnit: unit, интеграционные (PostgreSQL) и API e2e
	$(DEV) vendor/bin/phpunit

lint: ## PHP CS Fixer в режиме проверки
	$(DEV) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Исправить стиль кода
	$(DEV) vendor/bin/php-cs-fixer fix

phpstan: ## Статический анализ (level 8)
	$(DEV) vendor/bin/phpstan analyse --no-progress

check: lint phpstan test ## Все проверки бэкенда

fixtures: ## Перезаписать БД демо-данными (товары, атрибуты, изображения, пользователь)
	$(COMPOSE) exec app php bin/console app:fixtures:load --force

user: ## Создать пользователя: make user U=login P=password
	$(COMPOSE) exec app php bin/console app:user:create "$(U)" "$(P)"

frontend-install: ## Установить зависимости фронтенда
	cd frontend && npm ci

frontend-test: ## Unit-тесты Angular (Karma, ChromeHeadless)
	cd frontend && npm run test:ci

e2e: ## Playwright по поднятому стеку (make up)
	cd frontend && E2E_PASSWORD=$$(grep ^ADMIN_PASSWORD ../.env | cut -d= -f2) npx playwright test
