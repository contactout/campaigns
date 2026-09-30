---
title: Contributing
---

<!--@include: ../CONTRIBUTING.md-->

## Documentation site

The contributing guide above is included from [`CONTRIBUTING.md`](https://github.com/contactout/campaigns/blob/main/CONTRIBUTING.md).

This site lives in `docs-site/` (VitePress) and is published to [GitHub Pages](https://contactout.github.io/campaigns/) from `main`. It does not use the Laravel app's `package.json`.

```bash
cd docs-site
npm ci
npm run docs:dev       # http://localhost:5173/campaigns/
npm run docs:build     # writes .vitepress/dist
```

Node 22. Architecture prose is included from `docs/architecture.md`; edit that file rather than pasting a second copy here.
