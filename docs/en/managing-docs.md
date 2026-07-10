<!-- tiger:doc
title: Managing the docs
order: 10
visibility: admin
-->

# Managing the docs

*(You're reading this in the admin help center — an `admin`-visibility doc.)*

## The public route

Under **Docs → Settings**, the pretty public path (default `/docs`) can be renamed or turned off entirely. The canonical `/docs/index/docs` route always works regardless, so nothing ever breaks.

## The search index

The nav + search are built by scanning every content file and cached **per server**, rebuilt automatically the moment that server's content changes (a fingerprint check). You rarely touch it, but on the settings screen **Rebuild index** forces a rebuild on the current server — handy right after editing content, or to warm the cache.

In a multi-server fleet, each box self-heals on its own; warm them at deploy with a request to `/docs` per server.

## Adding docs

- **Platform docs** live in the private content repo (`content/<locale>/<collection>/`).
- **Module docs** live in each module's own `docs/` folder and appear automatically when the module is active — see **[Writing module docs](/docs/docs/writing-docs)**.
