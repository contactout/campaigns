---
title: Introduction
layout: home
hero:
  name: Campaigns
  text: Self-hosted email outreach
  tagline: Multi-step sequences from your own inboxes, with replies and bounces detected automatically.
  image:
    src: /connie.png
    alt: Connie, the ContactOut owl
  actions:
    - theme: brand
      text: Quick start
      link: /quick-start
    - theme: alt
      text: Architecture
      link: /architecture
features:
  - title: Campaigns
    details: Multi-step email sequences, recipients from contacts, start/pause lifecycle
  - title: Contacts
    details: Spreadsheet-style editor, lists as tabs, custom columns
  - title: Connections
    details: SMTP (+ optional IMAP), Gmail, or Outlook (BYO OAuth apps)
  - title: Sending
    details: Database queue, scheduled dispatch, placeholder merge fields
  - title: Engagement
    details: Open pixel, tracked links, signed unsubscribe
  - title: Replies & bounces
    details: IMAP or Gmail/Outlook API polling
---

# About

A self-hosted email outreach / campaign tool by [ContactOut](https://github.com/contactout).

**Why Campaigns?** It sends multi-step outreach sequences (cold and sales outreach) from your
own inboxes, with replies and bounces detected automatically. It is not a newsletter or bulk
email service provider like Listmonk or Mautic.

Team-owned campaigns, a contacts spreadsheet (lists included), SMTP / Gmail / Outlook
sending, open & click tracking, unsubscribe, and reply/bounce detection. Multi-user teams via
Laravel starter kit Fortify + teams.

Built with Laravel 13, Inertia React, and Tailwind. Designed to run on your own server
via Docker Compose.
