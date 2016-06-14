#!/bin/sh
php --version \
  && composer --version \
  && ./vendor/bin/phpcs --standard=psr2 -n --ignore=vendor,src/CNG . \
  && ./vendor/bin/phpunit -c ./phpunit.xml.dist