---
title: Architecture
---

# Architecture

A tour of how Campaigns is put together. For deployment, see [Quick start](./quick-start).

The sections from **Stack** onward are included from [`docs/architecture.md`](https://github.com/contactout/campaigns/blob/main/docs/architecture.md). Edit that file.

## Production services

```mermaid
flowchart TB
    user([Browser]) -->|HTTPS| caddy

    subgraph compose[Docker Compose]
        caddy[caddy<br/>TLS + reverse proxy] --> app[app<br/>Nginx + PHP-FPM]
        queue[[queue<br/>queue:work]]
        scheduler[[scheduler<br/>schedule:work]]
        mysql[(mysql<br/>MySQL 8.4)]
        app --> mysql
        queue --> mysql
        scheduler --> mysql
    end

    subgraph providers[External mail providers]
        smtp[SMTP / IMAP servers]
        gmail[Gmail API]
        msgraph[Microsoft Graph]
    end

    queue -->|send campaign email| providers
    queue -->|poll mailboxes| providers
```

Rectangles are request-serving services, double-bordered boxes are background processes, and the
right-hand group is outside your deployment. All three PHP containers run the same image and share
the database, which also backs the queue, cache, and sessions. The scheduler only enqueues work: it
dispatches due emails and mailbox checks as jobs that the `queue` worker runs.

| Service     | Role                                                            |
| ----------- | --------------------------------------------------------------- |
| `caddy`     | TLS termination (Let's Encrypt) → reverse proxy to the app      |
| `app`       | Nginx + PHP-FPM (Laravel)                                       |
| `queue`     | `php artisan queue:work` — sends campaign emails                |
| `scheduler` | `php artisan schedule:work` — due emails + reply/bounce polling |
| `mysql`     | MySQL 8.4                                                       |

<!--@include: ../docs/architecture.md#body-->
