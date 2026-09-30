# Contributing

Thanks for helping improve Campaigns.

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

`composer dev` is the one-command runner for day-to-day development (it invokes
`php artisan dev`); the commands above are the manual equivalent.

For anything that sends mail (SMTP connections, invitations), use a local mail catcher such as
[Mailpit](https://mailpit.axllent.org/) and point `MAIL_HOST` / `MAIL_PORT` at it.

PHP **8.4**, Node **22**, and Composer are expected. SQLite is the default local DB.

Production self-hosting uses Docker Compose — see [README.md](https://github.com/contactout/campaigns/blob/main/README.md).

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

## AI assistants

Project rules for AI coding assistants live in `.ai/rules/` and are committed. `AGENTS.md`
and `CLAUDE.md` are gitignored: they are generated locally by `php artisan boost:install`
(Laravel Boost, a dev dependency). Don't commit them.

## Scope notes

Campaigns is email-campaign focused. Large new surfaces (SMS, non-email steps, AI composer,
billing) should be discussed in an issue first.

## Code of conduct

This project follows the [Code of Conduct](https://github.com/contactout/campaigns/blob/main/CODE_OF_CONDUCT.md). Report security issues
privately via [SECURITY.md](https://github.com/contactout/campaigns/blob/main/SECURITY.md).

## License

By contributing, you agree that your contributions are licensed under the MIT License
([LICENSE](https://github.com/contactout/campaigns/blob/main/LICENSE)).
