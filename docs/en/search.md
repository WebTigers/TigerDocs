<!-- tiger:doc
parent: features
order: 10
title: Search
-->

# Search

Every surface has instant search. Hit **⌘K** (or **Ctrl-K**, or **/** when you're not typing) to open it; type to filter; arrow keys to move, Enter to open.

## What it searches

- **All collections at once** — platform docs and every module's docs together.
- **Titles and body text**, with title matches ranked first and a snippet around the hit.
- **Scoped by surface** — the public ⌘K searches `public` docs; the admin Help center's ⌘K searches `admin` docs (and is admin-only).

## How it stays fast

Search doesn't read files at query time. The build extracts each doc's plain text once and caches it, so a query is a scan over memory — see **[The search index](/docs/admin/help)** (admin) for how that cache works. There's also a **filter box** at the top of the sidebar for narrowing the current collection's nav without leaving the page.
