<!-- tiger:doc
parent: features
order: 20
title: Files & DB docs
-->

# Files & DB docs

A doc can come from **two sources**, checked in order — the same live-override pattern Tiger uses for config and translations.

## Files (the default)

Version-controlled `.md` / `.html` / `.phtml` files shipped with a module or the platform. This is how Tiger's own docs (and these) are published: lean, reviewable, and diff-able. No database required — a fresh install has working docs out of the box.

## DB docs (the override)

A `page` row of type `doc` (`Tiger_Model_Page`) resolved by slug **wins over a file**. Because it reuses the CMS content store, a DB doc gets org-scoping, versioning, publish gating, and the shortcode pipeline for free — so a **tenant can override a shipped doc**, or author their own help content, without touching a file.

```
DB doc (org-scoped)  ─┐
                      ├─ first hit wins → rendered through the same engine
file (shipped)       ─┘
```

Files are the shipped baseline; the DB is the per-install / per-tenant override tier. Both render through `Tiger_Cms_Renderer`, so markdown, HTML, and trusted PHTML behave identically wherever the body came from.
