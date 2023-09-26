# Docker commands
.PHONY: build-assets
build-assets:
	docker compose run --rm --user 1000:1000 node yarn build

.PHONY: upgrade-npm-packages
upgrade-npm-packages:
	docker compose run --rm --user 1000:1000 node yarn upgrade-interactive --latest
