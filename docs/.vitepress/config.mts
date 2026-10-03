import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'NativePHP Live Activities',
  description: 'Live Activities and Live Updates for NativePHP Mobile, driven by PHP.',
  base: '/nativephp-live-activities-docs/',
  lang: 'en-US',
  sitemap: { hostname: 'https://pauline-vos.nl/nativephp-live-activities-docs/' },
  themeConfig: {
    siteTitle: 'Live Activities',
    nav: [
      { text: 'Guide', link: '/getting-started' },
      { text: 'PHP API', link: '/php-api' },
      { text: 'Plugin', link: 'https://nativephp.com/plugins/vos/nativephp-live-activities' },
    ],
    sidebar: [
      { text: 'Start here', items: [
        { text: 'Getting started', link: '/getting-started' },
        { text: 'Platform support', link: '/platform-support' },
      ] },
      { text: 'PHP API', items: [
        { text: 'Activities and content', link: '/php-api' },
        { text: 'Deep links', link: '/deep-links' },
        { text: 'Capability checks', link: '/capabilities' },
        { text: 'Errors and recovery', link: '/errors' },
        { text: 'Testing your application', link: '/testing' },
      ] },
      { text: 'Artwork and configuration', items: [
        { text: 'Bundled and custom icons', link: '/icons' },
        { text: 'Runtime artwork', link: '/runtime-artwork' },
        { text: 'Configuration', link: '/configuration' },
      ] },
      { text: 'Platforms', items: [
        { text: 'iOS setup', link: '/ios' },
        { text: 'Android behavior', link: '/android' },
        { text: 'Roadmap', link: '/roadmap' },
      ] },
    ],
    search: { provider: 'local' },
    outline: [2, 3],
    socialLinks: [{ icon: 'github', link: 'https://github.com/paulinevos/nativephp-live-activities-docs' }],
    editLink: {
      pattern: 'https://github.com/paulinevos/nativephp-live-activities-docs/edit/main/docs/:path',
      text: 'Edit this page on GitHub',
    },
    footer: { message: 'Documentation for vos/nativephp-live-activities.' },
  },
})
