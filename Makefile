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

.PHONY: phploc
phploc:
	docker run -i -v `pwd`:/project jolicode/phaudit bash -c "phploc $(php_sources); exit $$?"

.PHONY: phpcs
phpcs:
	docker run -i -v `pwd`:/project jolicode/phaudit bash -c "phpcs $(php_sources) --extensions=php --ignore=vendor,app/cache,Tests/cache --standard=PSR2; exit $$?"

.PHONY: phpcpd
phpcpd:
	docker run -i -v `pwd`:/project jolicode/phaudit bash -c "phpcpd $(php_sources); exit $$?"

.PHONY: phpdcd
phpdcd:
	docker run -i -v `pwd`:/project jolicode/phaudit bash -c "phpdcd $(php_sources); exit $$?"

.PHONY: phpcs-fix
phpcs-fix:
	docker run --rm -i -v `pwd`:`pwd` -w `pwd` grachev/php-cs-fixer --rules=@Symfony --verbose fix $(php_sources)

# PHPUnit commands

.PHONY: phpunit
phpunit: vendor ./vendor/bin/phpunit ./phpunit.xml.dist
	docker-compose run --rm php ./vendor/bin/phpunit --coverage-text $(options)

.PHONY: phpunit-functional
phpunit-functional: vendor ./vendor/bin/phpunit ./phpunit_functional.xml.dist
	docker-compose run --rm php ./vendor/bin/phpunit -c phpunit_functional.xml.dist --coverage-text $(options)
