# Docker commands
.PHONY: build-assets
build-assets:
	docker compose run --rm --user 1000:1000 node yarn build
