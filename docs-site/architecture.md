---
title: Architecture
---

# Architecture

A tour of how Campaigns is put together. For deployment, see [Quick start](./quick-start).

The sections from **Stack** onward are included from [`docs/architecture.md`](https://github.com/contactout/campaigns/blob/main/docs/architecture.md). Edit that file.

## Production services

```
Internet → Caddy (HTTPS) → app (Nginx + PHP-FPM)
                              ├─ queue (campaign sends)
                              └─ scheduler (dispatch + mailbox checks)
                         → MySQL 8.4
```

| Service     | Role                                                            |
| ----------- | --------------------------------------------------------------- |
| `caddy`     | TLS termination (Let's Encrypt) → reverse proxy to the app      |
| `app`       | Nginx + PHP-FPM (Laravel)                                       |
| `queue`     | `php artisan queue:work` — sends campaign emails                |
| `scheduler` | `php artisan schedule:work` — due emails + reply/bounce polling |
| `mysql`     | MySQL 8.4                                                       |

<!--@include: ../docs/architecture.md#body-->
