set shell := ["bash", "-c"]

composer := "composer"
node := "docker compose run --rm --user \"$(id -u):$(id -g)\" node"

[group("Just")]
default: just-choose

alias help := just-help

[doc("Choose from available recipes")]
[group("Just")]
just-choose:
    @just --choose

[doc("List available recipes")]
[group("Just")]
just-help:
    @just --list

[doc("Format Justfile")]
[group("Just")]
just-format:
    @just --dump > justfile_formatted
    @mv justfile_formatted justfile

[doc("Install composer dependencies")]
[group("Composer")]
composer-install:
    {{ composer }} install

[doc("Install the node modules (in Docker)")]
[group("Assets")]
assets-install:
    {{ node }} npm ci

[doc("Build the assets into src/Resources/public (in Docker)")]
[group("Assets")]
assets-build:
    {{ node }} npm run build

[doc("Upgrade the node modules interactively (in Docker)")]
[group("Assets")]
assets-upgrade:
    {{ node }} npx npm-check-updates --interactive --target latest
    {{ node }} npm install

[doc("Run all coding-standard checks")]
[group("Coding standard")]
coding-standard-check: cs-check-php cs-check-composer

[doc("Apply all automated coding-standard fixes")]
[group("Coding standard")]
coding-standard-fix: cs-fix-php cs-fix-composer

[doc("Check PHP code sniffer")]
[group("Coding standard")]
cs-check-php:
    vendor/bin/phpcs --basepath=. --standard=config/phpcs.xml

[doc("Fix PHP code sniffer")]
[group("Coding standard")]
cs-fix-php *args="":
    vendor/bin/phpcbf --basepath=. --standard=config/phpcs.xml {{ args }}

[doc("Check composer files")]
[group("Coding standard")]
cs-check-composer:
    {{ composer }} validate
    {{ composer }} normalize --dry-run

[doc("Fix composer files")]
[group("Coding standard")]
cs-fix-composer:
    {{ composer }} normalize

[doc("Run static analysis checks")]
[group("Static analysis")]
static-analysis: sa-phpstan

[doc("Run PHPStan")]
[group("PHPStan")]
sa-phpstan:
    vendor/bin/phpstan --memory-limit=1G --configuration=config/phpstan.neon

[doc("Update PHPStan baseline")]
[group("PHPStan")]
sa-phpstan-update:
    vendor/bin/phpstan --memory-limit=1G --configuration=config/phpstan.neon --generate-baseline=config/phpstan-baseline.neon --allow-empty-baseline

[doc("Run security checks")]
[group("Security")]
security-analysis: security-analysis-packages

[doc("Run security checks for packages")]
[group("Security")]
security-analysis-packages:
    {{ composer }} audit

[doc("Run all tests")]
[group("Tests")]
test: test-php

[doc("Run PHP tests")]
[group("Tests")]
test-php *args="":
    vendor/bin/phpunit -c config/phpunit.xml.dist {{ args }}

[doc("Run all checks")]
[group("Aggregate")]
check: coding-standard-check static-analysis security-analysis test
