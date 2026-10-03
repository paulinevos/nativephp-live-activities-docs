# NativePHP Live Activities documentation

The full user documentation for `vos/nativephp-live-activities`, built with
[VitePress](https://vitepress.dev/) and deployed to GitHub Pages.

Site: https://paulinevos.github.io/nativephp-live-activities-docs/

## Local development

Use Node.js 22 or newer. VitePress is pinned to its stable release, with a
patched Vite 6 override for its development tooling:


```sh
npm ci
npm run docs:dev
```

To validate the production build and preview it:

```sh
npm run docs:build
npm run docs:preview
```

Preview the printed URL including `/nativephp-live-activities-docs/`.
The build fails on missing internal Markdown page links.

## Publishing with GitHub Pages

Create `paulinevos/nativephp-live-activities-docs`, push this repository to its
`main` branch, and select **Settings → Pages → Build and deployment → Source →
GitHub Actions**. The Pages workflow builds and deploys on pushes to `main` and
can also be run manually. Pull requests build without deploying.

If you rename the repository or use a custom domain, update `base` and `sitemap`
in `docs/.vitepress/config.mts` and the full-docs links in the package README.
Leave `.html` in README links so they work directly on GitHub Pages.

## Maintaining the docs

Edit Markdown in `docs/`. Keep this site as the full reference and the package
README as a short introduction with common PHP usage, a custom-icon example,
and links to the relevant guides. Update both repositories when the API changes.
Documentation currently follows the package working tree, including the fluent
`LiveActivity` API and activity deep links; check release availability when publishing.

Internal bridge contracts, build verification notes, and development plans remain
in the plugin repository. Generated site output and dependencies are ignored.
