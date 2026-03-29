# Oneshot Fantasy — Symfony application

See **[../docs/symfony-setup.md](../docs/symfony-setup.md)** for install steps, environment variables, and URLs (`/`, `/api`, `/api/docs`).

Quick start:

```bash
composer install
cp .env.local.dist .env.local   # optional overrides
php bin/console dbal:run-sql "SELECT 1"
php -S 127.0.0.1:8080 -t public
```
