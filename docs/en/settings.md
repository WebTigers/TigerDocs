<!-- tiger:doc
parent: administration
order: 10
title: Docs settings
visibility: admin
-->

# Docs settings

Find these under **Docs** in the admin Settings tree (`/docs/admin/settings`).

## The public route

By default the docs answer at **`/docs`**. From the settings screen you can:

- **Rename** the public path (e.g. serve them at `/help`).
- **Turn the pretty path off** entirely — the docs are still reachable at the canonical `/docs/index/docs`, so nothing ever breaks.
- **Reprioritize** it, for the rare case where two modules' pretty paths overlap.

These are stored in the live config tier (`tiger.routing.override.docs.*`), so a change takes effect on the next request — no deploy.

## Why the canonical route always works

The pretty `/docs` is an *alias*, applied only to URLs no real controller handles. The module's own screens — `/docs/admin/settings`, `/docs/admin/help` — are real controllers, so the alias never shadows them, and the docs are always reachable even with the alias off.
