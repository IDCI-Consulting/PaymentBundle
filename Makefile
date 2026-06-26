# Variables

php_sources ?= .

# Utils

vendor: composer.json composer.lock
	make -C $$PWD composer-install

.PHONY: composer-add-github-token
composer-add-github-token:
	docker-compose run --rm php composer config --global github-oauth.github.com $(token)

.PHONY: composer-update
composer-update:
	docker-compose run --rm php -d memory_limit=-1 /usr/local/bin/composer update $(options)

.PHONY: composer-install
composer-install:
	docker-compose run --rm php composer install $(options)

.PHONY: cs-check
cs-check:
	docker run -it --rm -v `pwd`:/code ghcr.io/php-cs-fixer/php-cs-fixer:$(php_fixer_version) fix $(php_sources) --rules=@Symfony --dry-run

.PHONY: cs-fix
cs-fix:
	docker run -it --rm -v `pwd`:/code ghcr.io/php-cs-fixer/php-cs-fixer:$(php_fixer_version) fix $(php_sources) --rules=@Symfony

# PHPUnit commands

.PHONY: phpunit
phpunit: vendor ./vendor/bin/phpunit ./phpunit.xml.dist
	docker-compose run --rm php ./vendor/bin/phpunit --coverage-text $(options)

.PHONY: phpunit-functional
phpunit-functional: vendor ./vendor/bin/phpunit ./phpunit_functional.xml.dist
	docker-compose run --rm php ./vendor/bin/phpunit -c phpunit_functional.xml.dist --coverage-text $(options)
