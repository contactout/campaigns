---
paths:
    - 'resources/js/pages/**'
---

# Pages

## Use the matching page layout and content shell

Follow resources/js/app.tsx layout mapping: auth pages use AuthLayout; settings and teams use AppLayout + SettingsLayout; other signed-in pages use AppLayout. For standard index/detail pages, wrap content in a centered max-w-6xl container with px-4 py-8 sm:px-6, responsive heading/action rows, and an actionable empty state; keep shared Heading and UI components. Full-viewport workspaces such as contacts/index.tsx may use a height-constrained flex shell with internal scrolling instead of the centered container, keeping controls, tabs, and pagination accessible. Check mobile width and dark-mode tokens when adding or changing page layouts.
