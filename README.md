# Tiger Docs

A public **documentation** module for [Tiger](https://github.com/WebTigers/tiger-core) — an
organized help site rendered in your app's active theme, at `/docs`.

Docs are **dual-source**: they render from either static **files** shipped in the module
*or* **database** pages, mixed freely. Files are the version-controlled baseline (present on a
fresh install, no DB rows); the database is the last-wins override tier — an install or a
single tenant can add or replace a doc without shipping a release. When both exist for a slug,
the database wins (and an org-scoped row beats a global one). Both sources render through the
same engine (`Tiger_Cms_Renderer`: Markdown / HTML / trusted PHTML).

Tiger ships **lean** — the platform's own docs live as the files in `content/`, the same ones
served at [tiger.webtigers.com/docs](https://tiger.webtigers.com/docs). Your app writes its own
product docs the same way.

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
| `/docs` | the landing page (a card per section from `content/manifest.json`) |
| `/docs/<slug>` | one doc, resolved DB-then-file, in your theme (nested slugs work: `/docs/guides/deploy`) |

## Authoring

Add a file under `content/en/` and list it in `content/manifest.json` — that's a doc. Use a
`page` row of `type = doc` (same slug) to override or add one on a live install without shipping
code. See **[Writing docs — files & DB](content/en/writing-docs.md)** for the full guide.

## How it's packaged

The **repository root is the module** — on install its contents become
`application/modules/docs/`. [`module.json`](module.json) is the manifest the installer reads
(and the basis of the Vendor Registry listing): slug, version, `requires`, what it `provides`
(routes / acl / content / migrations / assets), license, and pricing.

```
module.json          # manifest (installer + registry read this)
Bootstrap.php        # Docs_Bootstrap  — registers the /docs/<slug> route
controllers/         # Docs_IndexController → /docs landing + /docs/<slug>
models/Docs.php      # Docs_Model_Docs   — the dual-source resolver + nav tree
content/             # shipped docs (manifest.json + <locale>/*.md) — the default source
configs/acl.ini      # public (guest) access to the docs surface
views/scripts/       # theme-overridable views (landing, doc, sidebar)
languages/en/        # docs.* chrome strings
migrations/          # additive module migrations (none yet)
assets/              # published to public/_modules/docs on install
```

## License

BSD-3-Clause © WebTigers. See [LICENSE](LICENSE). "Tiger", "TigerDocs", and "WebTigers" are
trademarks of WebTigers (reserved, not licensed under the BSD terms). *(Public code is
reviewable; Tiger only installs modules from public repos — see the Tiger Module Installer
design.)*
