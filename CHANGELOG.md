# Changelog

All notable changes to **Tiger Docs** (`webtigers/docs`). Format follows
[Keep a Changelog](https://keepachangelog.com/); this project uses [SemVer](https://semver.org/)
— while `0.x`, the public API (`@api`) may still shift between minor versions.

## [0.5.0-beta] — 2026-07-11

### Added
- **Full-width toggle (Normal | Full).** A header control (right slot, `lg`+) switches the docs
  between the capped reading shell and full-width; the choice is remembered per-browser
  (localStorage `tigerdocs.layout`) and applied pre-paint via `data-docs-width` on `<html>` (no
  flash). Full-width uses equal **3 / 6 / 3** columns (left nav keeps its 3, the "on this page" rail
  goes 2→3, the article fills the middle) and caps the prose at ~52rem so lines stay readable; the
  rail-less landing stays 3 / 9. Docs-owned + docs-scoped (module `docs.layout.{js,css}` + the theme
  header region) — no theme or platform change.

### Fixed
- **"On this page" marker + `#anchor` deep-links were dead on every page with headings.**
  `_renderFile`/`_renderPage` captured `html` in the result array *before* `_pageNav()` injected the
  h2/h3 ids into it (evaluation order), so the TOC had ids the article headings lacked — every
  `getElementById` missed and the scrollspy bailed. Run `_pageNav()` before capturing `$html`.

### Changed
- **Reference anchors are now clean.** Method headings render as `name()` (→ a `#name` anchor + a
  tidy "on this page" entry) with the full signature in a code block below — instead of slugifying
  the whole signature into a monstrous anchor.

## [0.4.0-beta] — 2026-07-10

### Added
- **"Build reference" button in the admin** (Docs → Settings). Regenerates this server's API
  reference (docblocks → `tiger:doc` pages) into `var/docs-generated` and refreshes the index — the
  admin-panel equivalent of the `bin/build-reference.php` deploy hook. Admin-gated, per-server, via
  `Docs_Service_Settings::buildReference`.
- **`Docs_Reference_Generator::buildAll($appRoot, $locale)`** — the shared build orchestration
  (platform `Tiger_*` + every app module), now called by *both* the CLI hook and the admin button.

## [0.3.0-beta] — 2026-07-10

Generated reference becomes a **build artifact**, never committed.

### Added
- **A collection can draw from multiple directories.** `Docs_Model_Docs` now merges a collection
  from hand-written *and* generated sources — hand-written pages always win a same-id collision.
  This lets generated reference **add a "Reference" section** to a module's own docs collection
  instead of replacing anything.
- **Generated reference source** — the engine scans `<app>/var/docs-generated/<locale>/` (a
  gitignored, per-instance build area) as an extra source: it merges into a matching collection or
  stands up the platform `reference` collection.
- **`bin/build-reference.php` — the deploy/install build hook.** Rebuilds *all* reference for the
  instance in one shot: platform (`Tiger_*`) → the `reference` collection, and each app module →
  a Reference section in its own docs. Token-based, no app boot; auto-derives the app root.

### Changed
- **Generated reference is no longer committed anywhere.** It targets `var/docs-generated/` (rebuilt
  from code on every deploy, so it can't rot), not the module `docs/` folder and not the content
  repo. The single-target generator (`bin/reference.php`) no longer emits an `_index` in module mode
  (output stays pure so it can merge); platform mode emits a fallback `_index` a hand-written one wins.

### Removed
- The previously-committed generated reference pages from this module's `docs/en/` (the hand-written
  `reference-generator.md` guide stays). They're now built on the instance by the hook.

## [0.2.1-beta] — 2026-07-10

### Fixed
- **Build cache now stays inside the app root (cPanel-safe).** The cache's `sys_get_temp_dir()`
  fallback could land in a location a deploy can't clear (e.g. php-fpm `PrivateTmp`, or a shared/
  volatile `/tmp` on cPanel), so the index silently went stale. `_cacheDir()` no longer falls back
  to system tmp — the cache always lives at `<app>/var/cache/tiger-docs`, a known, user-writable,
  deploy-clearable path. If that isn't writable it simply rebuilds each request (correctness intact).
  Override with `tiger.docs.cache.dir`.

## [0.2.0-beta] — 2026-07-10

The stub becomes a real engine: zero-config, multi-source, dual-surface, distributed-cached.

### Added
- **Zero-config scan engine.** The `content/manifest.json` is gone. A collection is just a
  directory of markdown files; each file self-describes in a leading `<!-- tiger:doc … -->` block
  (`parent` / `order` / `title` / `header` / `visibility`). Nesting runs arbitrarily deep via
  `parent`; `order` is a float so inserts don't renumber; an `_index.md` gives a collection its
  label, order, and landing page.
- **Multi-source, self-documenting modules.** Every **active** module that ships a
  `docs/<locale>/` folder becomes its own collection (slug = module name) — drop a `docs/` folder
  in and it appears in the site; deactivate the module and it's gone. Toggle with `docs.modules.scan`.
- **Public / admin visibility.** One engine, two surfaces: `visibility: public` docs render on the
  public `/docs` site; `visibility: admin` docs render in a new **admin help center** at
  `/docs/admin/help` (admin shell, registered in the sidebar via `Tiger_Admin_Nav`). The engine
  never leaks a doc across surfaces.
- **Per-server build cache** (`Docs_Model_Index`). A fingerprint of every content file's
  `path|mtime|size` invalidates a serialized, opcache-friendly index — no DB, no coordination, so
  it self-heals per server in a fleet. Knobs: `tiger.docs.cache.{enabled,check,ttl,dir}`. The
  cache is a regenerable artifact (`var/cache/tiger-docs`, never committed).
- **Reference generator** (`bin/reference.php`). A token-based reader (no app boot) turns `@api`
  classes + their docblocks into `tiger:doc` reference pages that flow through the normal
  scan/cache/nav/search. Targets a specific module (ships reference *with* the module) or the
  whole platform (`--platform`, `Tiger_*` sectioned by namespace). Idempotent.
- **In-page search** (`Docs_Service_Search` over `/api`) powering a ⌘K launcher on both surfaces,
  scored over the cached plain text; `scope=admin` is admin-gated.
- **Docs reading experience:** vendored highlight.js syntax highlighting; an "On this page"
  scrollspy with a sliding marker; a prev/next pager; heading spacing; sticky sidebars that clear
  the floating header.
- **Admin settings** (`/docs/admin/settings`) to retarget the `/docs` route prefix and rebuild the
  index; **`AGENTS.md`** documenting how docs are built in Tiger; a full self-docs POC (public +
  admin, `en`).

### Changed
- Docblocks across the module standardized to the reference contract (`@api`, `@param`/`@return`
  on public methods) so the generator produces clean pages.
- Module assets are cache-busted through `$this->asset()`.

### Fixed
- Sticky doc sidebars sat at the navbar's `z-index` and painted over header dropdowns — dropped
  below it so header menus overlay correctly.

## [0.1.0] — 2026-07-08

### Added
- Initial stub: the installable-module skeleton (`module.json`), a public `/docs` landing, and the
  dual-source (files + DB) resolver.
