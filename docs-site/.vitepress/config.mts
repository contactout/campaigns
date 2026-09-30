import { defineConfig } from 'vitepress'

// Project site: https://contactout.github.io/campaigns/
export default defineConfig({
    title: 'Campaigns',
    description:
        'Self-hosted email outreach. Multi-step sequences from your own inboxes.',
    base: '/campaigns/',
    lang: 'en-US',
    markdown: {
        languageAlias: {
            env: 'ini',
        },
    },
    themeConfig: {
        logo: '/connie.svg',
        siteTitle: 'Campaigns',
        nav: [
            { text: 'Quick start', link: '/quick-start' },
            { text: 'Architecture', link: '/architecture' },
            {
                text: 'GitHub',
                link: 'https://github.com/contactout/campaigns',
            },
        ],
        sidebar: [
            {
                text: 'Guide',
                items: [
                    { text: 'Introduction', link: '/' },
                    { text: 'Quick start', link: '/quick-start' },
                    { text: 'Architecture', link: '/architecture' },
                    { text: 'Connections', link: '/connections' },
                    { text: 'Operations', link: '/operations' },
                    {
                        text: 'Deliverability & compliance',
                        link: '/deliverability',
                    },
                    { text: 'Contributing', link: '/contributing' },
                ],
            },
        ],
        socialLinks: [
            {
                icon: 'github',
                link: 'https://github.com/contactout/campaigns',
            },
        ],
        search: {
            provider: 'local',
        },
        outline: { level: [2, 3] },
        editLink: {
            pattern:
                'https://github.com/contactout/campaigns/edit/main/docs-site/:path',
            text: 'Edit this page on GitHub',
        },
        footer: {
            message: 'Released under the MIT License.',
            copyright: 'Copyright © 2026 ContactOut',
        },
    },
})
