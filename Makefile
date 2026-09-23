.DEFAULT_GOAL := ajuda
.PHONY: ajuda subir descer logs instalar banco testar e2e revisar formatar imagem manifestos

ajuda: ## Lista os alvos
	@grep -E '^[a-z-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-14s %s\n", $$1, $$2}'

subir: ## Sobe o ambiente local
	docker compose up -d

descer: ## Derruba o ambiente e mantém o volume do banco
	docker compose down

logs: ## Acompanha o log da aplicação
	docker compose logs -f api

instalar: ## Instala as dependências
	composer install
	npm ci

banco: ## Cria o banco de teste e carrega os dados de exemplo
	php bin/console doctrine:schema:create --env=test
	php bin/console doctrine:fixtures:load --env=test -n

testar: ## A bateria inteira, sem navegador
	php vendor/bin/phpunit

e2e: ## Playwright, no desktop e no celular
	npx playwright test

revisar: ## O mesmo que o CI cobra
	PHP_CS_FIXER_IGNORE_ENV=1 php vendor/bin/php-cs-fixer fix --dry-run --diff
	php vendor/bin/phpstan analyse --memory-limit=1G
	composer audit

formatar: ## Aplica o formatador
	PHP_CS_FIXER_IGNORE_ENV=1 php vendor/bin/php-cs-fixer fix

imagem: ## Constrói a imagem de produção
	docker build --target producao -t almanaque:1.0.0 .

manifestos: ## Confere os manifestos do Kubernetes
	python k8s/conferir-manifestos.py
