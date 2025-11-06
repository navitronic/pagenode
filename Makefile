.PHONY: build clean deps

build: vendor/autoload.php
	php -d phar.readonly=0 build-phar

deps: vendor/autoload.php

vendor/autoload.php: composer.lock
	composer install --no-interaction --prefer-dist

clean:
	rm -f pagenode.phar
