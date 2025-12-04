SHELL := /bin/bash

tests:
	symfony console doctrine:database:drop --force --env=test || true
	symfony console doctrine:database:create --env=test
	symfony console doctrine:migrations:migrate -n --env=test
	symfony console doctrine:fixtures:load -n --env=test
	symfony php bin/phpunit $(MAKECMDGOALS)
.PHONY: tests
init:
	symfony server:stop
	symfony server:start
	symfony run -d --watch=config,src,templates,vendor/composer/installed.json symfony console messenger:consume async
.PHONY: init