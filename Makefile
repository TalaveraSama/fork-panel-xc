SHELL := /bin/bash
FRONTEND := frontend
PHP ?= php

.PHONY: help install build frontend-install migrate serve dev check clean

help:
	@echo "Fork Panel XC"
	@echo ""
	@echo "  make install           Install system deps + app (interactive, needs root)"
	@echo "  make frontend-install  Install frontend dependencies"
	@echo "  make build             Build the production frontend into frontend/dist"
	@echo "  make dev               Run API (:8080) and Vite dev server (:3000)"
	@echo "  make migrate           Apply pending database migrations"
	@echo "  make check             Parse the API PHP files and build the frontend"
	@echo "  make clean             Remove build output"

install:
	sudo python3 install.py

frontend-install:
	cd $(FRONTEND) && npm install

build: frontend-install
	cd $(FRONTEND) && npm run build

migrate:
	$(PHP) console.php db:migrate

check:
	cd $(FRONTEND) && npm ci --no-audit --no-fund && npm run check:php && npm run build

serve:
	$(PHP) console.php serve 8080

dev:
	@echo "API on :8080, frontend on :3000 (Vite proxies /api)"
	@$(MAKE) -j2 serve dev-frontend

dev-frontend:
	cd $(FRONTEND) && npm run dev

clean:
	rm -rf $(FRONTEND)/dist $(FRONTEND)/node_modules
