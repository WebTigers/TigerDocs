<!-- tiger:doc
parent: administration
order: 20
title: The search index
visibility: admin
-->

# The search index

The nav + search are built by **scanning** the content files (platform + every active module's `docs/` folder) and **cached per server**. You rarely think about it, but here's what's happening.

## It self-heals

A cheap **fingerprint** of the content (every file's path, mtime, and size) is the change signal. When a server's content changes, its fingerprint changes, and that server rebuilds its cache automatically on the next request. No database, no coordination — each box heals itself.

## Rebuild button

On the settings screen, **Rebuild index** forces a rebuild on *this* server — handy right after editing content, or to warm a cold cache.

## In a fleet

Every server keeps its own cache and self-heals independently, so a rolling deploy just converges. To skip the first-request rebuild after a deploy, **warm each server** with a request to `/docs`.

## Config knobs

Live-override settings (`tiger.docs.*`), no deploy:

| Key | Default | Effect |
|---|---|---|
| `docs.cache.enabled` | on | use the build cache at all |
| `docs.cache.check` | on | verify the fingerprint each request (off = "trust the deploy", never re-scan) |
| `docs.cache.ttl` | 10 | seconds between fingerprint walks (throttle for hot servers) |
| `docs.cache.dir` | auto | where the cache file lives (app `var/cache`, else system temp) |
| `docs.modules.scan` | on | aggregate active modules' `docs/` folders |
