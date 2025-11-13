.PHONY: up down install migrate test phpcs fixer psalm quality

up:
	docker compose up -d

down:
	docker compose down

install:
	docker compose exec php composer install

migrate:
	docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

test:
	docker compose exec php php bin/phpunit

phpcs:
	docker compose exec php vendor/bin/phpcs

fixer:
	docker compose exec php vendor/bin/php-cs-fixer fix

psalm:
	docker compose exec php vendor/bin/psalm

quality:
	docker compose exec php composer ci
