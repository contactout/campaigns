# Contributing

Thanks for helping improve MMOS.

## Development setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm ci
npm run dev          # Vite; separate terminal:
php artisan serve
php artisan queue:work
php artisan schedule:work
```

PHP **8.4**, Node **22**, and Composer are expected. SQLite is the default local DB.

Production self-hosting uses Docker Compose — see [README.md](README.md).

## Before you open a PR

```bash
php artisan test --compact
vendor/bin/phpstan analyse --no-progress
vendor/bin/pint --dirty
npm run types:check
npm run check
```

- Prefer small, focused pull requests
- Add or update Pest tests for backend behavior changes
- Do not commit `.env`, secrets, or personal OAuth credentials
- Match existing code style (Laravel Pint / project Prettier via `vp check`)

## Scope notes

MMOS is email-campaign focused. Large new surfaces (SMS, non-email steps, AI composer,
billing) should be discussed in an issue first.

## License

By contributing, you agree that your contributions are licensed under the MIT License
([LICENSE](LICENSE)).
