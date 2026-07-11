# Documentation

*A documentation site for your Tiger app — organized, searchable help pages that render in your
active theme, and modules that document themselves.*

> **`TIGER.md` is the vendor description** for a Tiger module — the human pitch the Module
> Installer shows before you install (pulled straight from the repo over cURL). Keep it current;
> it always reflects the repo. The machine-readable manifest is [`module.json`](module.json).

## What it does

- A public **`/docs`** site rendered in your active theme (override the views to restyle), with an
  in-page **⌘K search**.
- **Zero-config authoring** — a doc is just a markdown file with a small `tiger:doc` header. No
  manifest, no registration; drop the file in and it's a doc.
- **Self-documenting modules** — every active module that ships a `docs/` folder becomes its own
  section automatically.
- An **admin help center** at `/docs/admin/help` for operator docs, separate from the public site.
- **Generated API reference** — turn a module's `@api` classes into reference pages straight from
  their docblocks (`bin/reference.php`), shipped with the module.

## Features

| | |
|---|---|
| **Themed** | Renders in your active theme; every view is overridable. |
| **Zero-config** | Install → activate → `/docs` is live. Author by dropping markdown files. |
| **Multi-source** | Platform docs + each active module's own `docs/` folder, aggregated automatically. |
| **Two surfaces** | `public` docs on `/docs`; `admin` docs in the in-app help center. |
| **Dual-tier** | Version-controlled files, with a database last-wins override for live installs. |
| **Searchable** | ⌘K launcher on both surfaces, backed by a per-server build cache. |
| **Self-referencing** | Generates its own API reference from docblocks. |
| **Distributed-safe** | Fingerprint-invalidated per-server cache — no DB, no coordination. |
| **Localized** | Language-only locales, like the rest of Tiger. |

## Requirements

- Tiger ≥ 1.0, PHP ≥ 8.1 (see [`module.json`](module.json) `requires`).

## Screenshots

*(none yet.)*

## Changelog

See [CHANGELOG.md](CHANGELOG.md). Current: **0.5.0-beta** — the zero-config, multi-source engine
(scan + build cache + docblock reference generator) plus a full-width docs toggle.

## License & support

BSD-3-Clause © WebTigers. See [LICENSE](LICENSE). Issues + PRs welcome at
[github.com/WebTigers/TigerDocs](https://github.com/WebTigers/TigerDocs).
This module is **free** — no pro tier.
