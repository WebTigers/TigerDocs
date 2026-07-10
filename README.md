# Tiger Docs

A **documentation** module for [Tiger](https://github.com/WebTigers/tiger-core) — an organized,
searchable help site rendered in your app's active theme, at `/docs`.

Docs are **zero-config**: there is no manifest to maintain. A doc is just a markdown file with a
small metadata header; the engine scans directories on demand and caches the result per server.
Drop a file in, it's a doc. It's also **multi-source** — every active module that ships a
`docs/` folder becomes its own section, so modules document themselves — and **dual-tier**: files
are the version-controlled baseline, and the database is a last-wins override so an install or a
single tenant can add or replace a doc without shipping a release. Everything renders through one
engine (`Tiger_Cms_Renderer`: Markdown / HTML / trusted PHTML).

Tiger ships **lean** — the platform's own docs are just the files in `content/`, the same ones
served at [tiger.webtigers.com/docs](https://tiger.webtigers.com/docs). Your app and its modules
write their docs the same way.

> **Authoring or extending docs?** Read [AGENTS.md](AGENTS.md) — the full guide to how docs are
> built in Tiger (the `tiger:doc` block, collections, visibility, the reference generator).

## Install

**From the admin (once the Module Installer ships):** *Modules → Add New →* search "docs", or
*Install from URL* and paste this repo's URL:

```
https://github.com/WebTigers/TigerDocs
```

**From the CLI:**

```
vendor/bin/tiger module:install https://github.com/WebTigers/TigerDocs
vendor/bin/tiger module:activate docs
```

The installer downloads a pinned release, verifies it, extracts the repo into
`application/modules/docs/`, runs the module's migrations, and publishes its assets. Because
it's an ordinary ZF1 module it's then auto-discovered — no core files touched.

## Routes

| URL | Renders |
|---|---|
| `/docs` | the **guide** landing (default, prefix-less collection) |
| `/docs/<slug>` | one guide doc (nested slugs work: `/docs/getting-started/quick-start`) |
| `/docs/<collection>/<slug>` | a doc in another collection (e.g. a module's docs) |
| `/docs/admin/help` | the **admin help center** — `visibility: admin` docs, in the admin shell |
| `/docs/admin/settings` | admin settings (retarget the `/docs` prefix, rebuild the index) |

`/docs` is a **route override** declared in `Docs_Bootstrap` (retargetable from Settings, e.g. to
`/help`); the canonical route is `docs/index/docs`.

## Authoring

Add a markdown file under a content directory and give it a `tiger:doc` header — that's a doc, no
registration:

```markdown
<!-- tiger:doc
parent:     getting-started
order:      20
title:      Add docs to a module
visibility: public
-->

# Add docs to a module
…
```

- **Platform docs** live in `content/<locale>/<collection>/`. **Module docs** live in that
  module's own `docs/<locale>/` folder (self-documenting modules).
- `visibility: public` (default) shows on `/docs`; `visibility: admin` shows in the admin help
  center. `header: true` makes a section; `parent:` nests arbitrarily deep.
- **API reference is generated, not written** — `bin/reference.php <module-dir>` turns `@api`
  classes + docblocks into `tiger:doc` pages that ship with the module.
- **Live override:** a `page` row of `type = doc` (same slug) adds or replaces a doc on a running
  install without a release (DB wins; org-scoped beats global).

See **[AGENTS.md](AGENTS.md)** and the in-app **Authoring** section at `/docs` for the full guide.

## How it's packaged

The **repository root is the module** — on install its contents become
`application/modules/docs/`. [`module.json`](module.json) is the manifest the installer reads
(and the basis of the Vendor Registry listing): slug, version, `requires`, what it `provides`
(routes / acl / content / migrations / assets), license, and pricing.

```
module.json          # manifest (installer + registry read this)
AGENTS.md            # how docs are built in Tiger (author-facing)
Bootstrap.php        # Docs_Bootstrap  — /docs route override, admin settings + help route/nav
controllers/         # IndexController → public /docs; AdminController → settings + help center
models/Docs.php      # Docs_Model_Docs  — the zero-config, multi-source, dual-tier engine
models/Index.php     # Docs_Model_Index — the per-server, fingerprint-invalidated build cache
services/            # Search (⌘K /api) + Settings (route-override config)
bin/reference.php    # the API-reference generator (docblocks → tiger:doc pages)
content/             # platform docs — content/<locale>/<collection>/*.md
docs/                # this module's OWN docs (public + admin) — the self-documenting POC
configs/acl.ini      # public (guest) access to the docs surface
views/scripts/       # theme-overridable views (landing, doc, sidebar, admin help)
languages/en/        # docs.* chrome strings
migrations/          # additive module migrations
assets/              # published to public/_modules/docs on install
```

## License

BSD-3-Clause © WebTigers. See [LICENSE](LICENSE). "Tiger", "TigerDocs", and "WebTigers" are
trademarks of WebTigers (reserved, not licensed under the BSD terms). *(Public code is
reviewable; Tiger only installs modules from public repos — see the Tiger Module Installer
design.)*
