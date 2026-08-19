# Makefile for Docker management

.PHONY: help up down restart logs ps build migrate seed test bash-app bash-queue reverb-status clean

help:
	@echo "Available commands:"
	@echo "  make up          - Start all Docker containers in background"
	@echo "  make down        - Stop and remove all containers"
	@echo "  make restart     - Restart all containers"
	@echo "  make logs        - Tail logs for all containers"
	@echo "  make ps          - List running containers status"
	@echo "  make build       - Rebuild Docker images without cache"
	@echo "  make migrate     - Run database migrations inside app container"
	@echo "  make seed        - Run database seeders inside app container"
	@echo "  make test        - Run test suite inside app container"
	@echo "  make bash-app    - Open bash shell inside Laravel app container"
	@echo "  make bash-queue  - Open bash shell inside Queue container"
	@echo "  make clean       - Stop containers and delete volumes (warning: data loss)"

up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose restart

logs:
	docker compose logs -f

ps:
	docker compose ps

build:
	docker compose build --no-cache

migrate:
	docker compose exec app php artisan migrate

seed:
	docker compose exec app php artisan db:seed

test:
	docker compose exec app php artisan test

bash-app:
	docker compose exec -it app sh

bash-queue:
	docker compose exec -it queue sh

clean:
	docker compose down -v
