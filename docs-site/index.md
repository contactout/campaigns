---
title: Introduction
---

# Campaigns

A self-hosted email outreach / campaign tool by [ContactOut](https://github.com/contactout).

**Why Campaigns?** It sends multi-step outreach sequences (cold and sales outreach) from your
own inboxes, with replies and bounces detected automatically. It is not a newsletter or bulk
email service provider like Listmonk or Mautic.

Team-owned campaigns, a contacts spreadsheet (lists included), SMTP / Gmail / Outlook
sending, open & click tracking, unsubscribe, and reply/bounce detection.

Built with Laravel 13, Inertia React, and Tailwind. Designed to run on your own server
via Docker Compose.

## Features

- **Campaigns** — multi-step email sequences, recipients from contacts, start/pause lifecycle
- **Contacts** — spreadsheet-style editor, lists as tabs, custom columns
- **Connections** — SMTP (+ optional IMAP), Gmail, or Outlook (BYO OAuth apps)
- **Sending** — database queue, scheduled dispatch, placeholder merge fields
- **Engagement** — open pixel, tracked links, signed unsubscribe
- **Replies & bounces** — IMAP or Gmail/Outlook API polling
- **Teams** — multi-user teams (Laravel starter kit Fortify + teams)
