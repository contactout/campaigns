# Security Policy

## Supported versions

Security fixes are applied on the latest `main` branch of this repository.

## Reporting a vulnerability

Please **do not** open a public GitHub issue for security problems.

Email **security@contactout.io** with:

- A description of the issue and its impact
- Steps to reproduce (PoC if available)
- Affected version / commit if known

We aim to acknowledge reports within a few business days. Please give us a
reasonable window to investigate and ship a fix before any public disclosure.

## Self-hosted operators

- Keep `APP_DEBUG=false` and a strong unique `APP_KEY` in production
- Keep `REGISTRATION_ENABLED=false` on public internet deployments. The first
  user can always register; everyone else needs a team invitation. Only enable
  open registration if you accept strangers sending email from your domain
- System mail (email verification, password resets, invitations) needs a working
  `MAIL_*` configuration. If you have none, mark a user verified from the server
  with `php artisan campaigns:verify-user user@example.com`
- Treat mailer connection tokens and SMTP passwords as secrets (they are
  stored encrypted at rest via Laravel’s encrypted cast)
- Rotate OAuth client secrets if you suspect compromise
