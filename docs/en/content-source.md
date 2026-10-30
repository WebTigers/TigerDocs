<!-- tiger:doc
parent: administration
order: 35
title: Fetching the manual from one path
-->

# Fetching the manual from one path

Content that ships *inside* something can only change by rebuilding that thing. If the manual
travels in a module release, fixing a typo means cutting a release; if it is baked into a machine
image, it means rebuilding the image. Neither is a sensible price for a sentence.

So the manual can instead live at **one path every server reads** — an S3 prefix, usually with a
CDN in front of it. Publish a page and every server picks it up on its own, with no deploy, no
release and no image rebuild.

## Turning it on

**Settings → Docs → Content source.** Set the **Content URL** to the base of the published
content, for example:

```
https://cdn.webtigers.com/docs
```

That path must hold the content tree (`<locale>/<collection>/<page>.md`) and an **`index.json`**
alongside it. Leave the field blank and nothing changes — the content that ships with the module,
or with a content module, serves as it always has.

The URL must be `https`. The files it returns are rendered into every page of the docs site, and
content fetched over plain `http` can be rewritten in transit.

## What a server actually does

Each server keeps its own copy under `var/docs-remote` and refreshes it **every 15 minutes**:

1. Reads `index.json` — the list of every content file with its SHA-256 and size.
2. Compares it against the copy it already has.
3. Downloads only the files whose hash differs, checks each one against the manifest, and writes
   it into place.
4. Deletes anything the manifest no longer lists.

When nothing has changed that is **one HTTP request**, which is why the check can afford to be
frequent. The nav and search index notice the changed files by themselves, so there is no cache to
clear.

**Fetch content** on the settings screen does it immediately on *that* server; the others refresh
on their own schedule. At deploy, `php application/modules/docs/bin/fetch-content.php` does the
same from the command line, so a new server serves the current manual on its first request rather
than its first scheduled tick.

A fetch never happens while rendering a page. It is network I/O, and a doc page must not wait on
it.

## Where it sits among the other sources

Highest priority first:

1. **`docs.content_dir`** — a directory named outright. The deliberate local override.
2. **The fetched content** — the published source of truth.
3. **A content module** (`provides.content`) — see [Where the manual comes from](/docs/docs/content-modules).
4. **This module's own `content/`** — what a bare install renders.

Sources are **additive**: a collection present in more than one merges, and the higher one wins a
page they share. Two consequences worth knowing:

- Until the first fetch lands, the sources below it serve. A new server is never blank, and a
  content module installed alongside is the offline floor.
- A page the published content does not carry still resolves from a lower source. That is usually
  what you want; if a page seems to survive being deleted upstream, this is why.

## Checking what a server is serving

The settings screen reports the file count, the publish stamp from the manifest, and when that
server last fetched. Every docs page also carries the winning source as a meta tag
(`remote <stamp>`), so a page can never claim a version it is not being rendered from — which is
the thing that goes wrong quietly in a fleet, where one server can sit a long way behind and still
answer `200`.

## What will not be fetched

Only non-executable content types (`.md`, `.html`, `.json`, `.txt`, images). A `.phtml` doc is
rendered as trusted code, so one is never accepted from a network source — publishing a file to a
bucket must not be a way to run code on every server. Executable content arrives only through a
release.

A file whose bytes do not match the manifest's hash is refused rather than installed, and the fetch
reports it. A path that tries to escape the content directory is refused outright.
