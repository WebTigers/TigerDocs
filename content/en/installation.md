# Installation

Tiger Docs is a standard installable Tiger module — it drops into `application/modules/docs/`
and is auto-discovered by ZF1's module scan. It touches no core file.

## Install

Once the Tiger Module Installer is wired up:

```
bin/tiger module:install webtigers/tiger-docs
```

Or install it by hand — clone the repo into your modules directory:

```
git clone https://github.com/WebTigers/TigerDocs application/modules/docs
```

The module's root **is** the module: its contents land in `application/modules/docs/`, so the
manifest's `slug` (`docs`) becomes the module name and the surface mounts at **`/docs`**.

## What you get

- **`/docs`** — the landing page (a card per section from `content/manifest.json`).
- **`/docs/<slug>`** — one doc, resolved from the DB then a file, rendered in your theme.
- A public ACL rule (`configs/acl.ini`) so the whole surface is readable by everyone.

Nested slugs work too: `content/en/guides/deploy.md` answers at `/docs/guides/deploy`.

## Verify

Visit `/docs` in a browser. You should see this documentation — served straight from the
files in `content/en/`, no database required. Next: **[Writing docs](/docs/writing-docs)**.
