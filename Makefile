# Docker commands
.PHONY: node-modules-install
node-modules-install:
	docker compose run --rm --user 1000:1000 node npm ci

.PHONY: build-assets
build-assets:
	docker compose run --rm --user 1000:1000 node npm run build

.PHONY: upgrade-npm-packages
upgrade-npm-packages:
	docker compose run --rm --user 1000:1000 node npx npm-check-updates --interactive --target latest
	docker compose run --rm --user 1000:1000 node npm install
