# Tiger Docs

A public **documentation** module for [Tiger](https://github.com/WebTigers/tiger-core) — an
organized, searchable help site rendered in your app's active theme.

> **Status: stub.** This is the first *installable* Tiger module — the canary for the Tiger
> Module Installer. Today it registers a public `/docs` landing page; the docs browser
> (article tree + search + content pages) is the next build.

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

## How it's packaged

The **repository root is the module** — on install its contents become
`application/modules/docs/`. [`module.json`](module.json) is the manifest the installer reads
(and the basis of the Vendor Registry listing): slug, version, `requires`, what it `provides`
(routes / acl / migrations / assets), license, and pricing.

```
module.json          # manifest (installer + registry read this)
Bootstrap.php        # Docs_Bootstrap  (auto-discovered)
controllers/         # Docs_IndexController → /docs
configs/acl.ini      # public (guest) access to the docs surface
views/scripts/       # theme-overridable views
languages/en/        # docs.* strings
migrations/          # additive module migrations (none yet)
assets/              # published to public/_modules/docs on install
```

## License

MIT © WebTigers. See [LICENSE](LICENSE). *(Public code is reviewable; Tiger only installs
modules from public repos — see the Tiger Module Installer design.)*
