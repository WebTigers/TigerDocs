<!-- tiger:doc
parent: authoring
order: 30
title: Public & admin docs
-->

# Public & admin docs

One `docs/` folder serves **two audiences**. The `visibility` field decides which surface a doc appears on:

- **`public`** (the default) — the public docs site at **`/docs`**. For your users and developers.
- **`admin`** — the in-app **help center** in the admin shell (**Help** in the sidebar). For operators: *how to configure and run this module.*

## One folder, both surfaces

```
billing/docs/en/
├── _index.md        # visibility: public  → the collection shows publicly
├── overview.md      # visibility: public  → /docs
└── configure.md     # visibility: admin   → the admin Help center
```

The `Billing` section appears in **both** places, but each surface only shows the docs meant for it — `overview` on `/docs`, `configure` in Help. Search, nav, and direct URLs are all scoped: a public URL will never serve an admin doc, and vice-versa.

## Inheritance

A file's visibility defaults to its collection's `_index` visibility (which itself defaults to `public`). So set `visibility: admin` on the `_index` and *every* page in that folder is admin unless it says otherwise — handy for an operations-only collection.

> This very page is `public`. The **[Administration](/docs/admin/help)** section of these docs is `admin` — you'll only find it in the Help center.
