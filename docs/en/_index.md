<!-- tiger:doc
title: TigerDocs
order: 50
visibility: public
-->

# TigerDocs

TigerDocs is Tiger's documentation module — and this collection is it **documenting itself**. Everything you're reading lives in the module's own `docs/` folder; there's no manifest, no database, no build step. It's the reference implementation of the paradigm it provides.

## The one big idea

**Documentation is files, not config.** The content directory *is* the table of contents. Drop a markdown file in the right folder and it appears; delete it and it's gone; reorder by bumping a number. Each file self-describes in a tiny HTML comment, and the engine assembles the nav, the search, and two audiences from that alone.

- **Any module can document itself** — drop a `docs/` folder in the module root and it becomes a section here. See **[Add docs to a module](/docs/docs/quick-start)**.
- **Two audiences, one folder** — `public` docs land on `/docs`; `admin` docs land in the in-app help center. See **[Public & admin docs](/docs/docs/visibility)**.
- **Zero-config, self-healing** — scanned, cached per server, and rebuilt automatically when content changes.

New here? Start with **[Add docs to a module](/docs/docs/quick-start)**, then skim **[The tiger:doc block](/docs/docs/metadata)**.
