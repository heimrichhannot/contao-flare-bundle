.PHONY: help docs-setup docs-remove php composer

# Configuration
DOCS_DIR = docs
DOCS_BRANCH = docs/main

help: ## Show this help menu
	@echo "Available commands:"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

php: ## Run php command in docker
	docker compose run --rm php $(filter-out $@,$(MAKECMDGOALS))

composer: ## Run composer command in docker
	docker compose run --rm composer $(filter-out $@,$(MAKECMDGOALS))

phpstan: ## Run PHPStan static analysis
	docker compose run --rm php vendor/bin/phpstan analyse --memory-limit=512M

docs-setup: ## Setup the Docusaurus worktree environment locally
	@if [ -d "$(DOCS_DIR)" ]; then \
		echo "Directory $(DOCS_DIR) already exists."; \
		exit 1; \
	fi
	@echo "Ensuring $(DOCS_DIR) is ignored..."
	@grep -q "^$(DOCS_DIR)/$$" .gitignore 2>/dev/null || echo "\n# Ignore documentation worktree\n$(DOCS_DIR)/" >> .gitignore
	@echo "Fetching latest branches..."
	@git fetch origin
	@echo "Creating worktree..."
	@git worktree add $(DOCS_DIR) docs/main
	@echo "Docs ready! cd into $(DOCS_DIR) and run 'npm install'."

docs-remove: ## Safely remove the local Docusaurus worktree
	@if [ ! -d "$(DOCS_DIR)" ]; then \
		echo "Directory $(DOCS_DIR) does not exist. Nothing to remove."; \
		exit 0; \
	fi
	@echo "Safely removing the git worktree..."
	@git worktree remove $(DOCS_DIR)
	@echo "Worktree removed successfully."

# Handle arguments for shortcuts
%:
	@:
