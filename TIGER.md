# Documentation

*A public documentation site for your Tiger app — organized, searchable help pages that render
in your active theme.*

> **`TIGER.md` is the vendor description** for a Tiger module — the human pitch the Module
> Installer shows before you install (pulled straight from the repo over cURL). Keep it current;
> it always reflects the repo. The machine-readable manifest is [`module.json`](module.json).

## What it does

- A public **`/docs`** section rendered in your theme (override the views to restyle).
- *(planned)* An article tree, full-text search, and per-page content — authored in the admin,
  stored in the DB like the CMS.

## Features

| | |
|---|---|
| **Themed** | Renders in your active theme; every view is overridable. |
| **Zero-config** | Install → activate → `/docs` is live. No settings required. |
| **Localized** | Language-only locales, like the rest of Tiger. |

## Requirements

- Tiger ≥ 1.0, PHP ≥ 8.1 (see [`module.json`](module.json) `requires`).

## Screenshots

*(none yet — this is a stub.)*

## Changelog

- **0.1.0** — initial stub: public `/docs` landing + the installable-module skeleton.

## License & support

MIT © WebTigers. Issues + PRs welcome at
[github.com/WebTigers/tiger-docs](https://github.com/WebTigers/tiger-docs).
This module is **free** — no pro tier.
