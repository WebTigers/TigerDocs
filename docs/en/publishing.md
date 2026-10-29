<!-- tiger:doc
parent: administration
order: 40
title: Publishing content to a fleet
-->

# Publishing content to a fleet

One server reads its own `content/` directory and nothing else needs saying. A *fleet* is harder:
the content has to reach every server, including ones that did not exist when it was published.

Tiger treats docs content as a **release**, not as a directory to keep in step. A publisher writes
one immutable artifact and a small pointer naming it:

```
<base>/current.json           {"sha":"a1b2c3…","artifact":"docs-a1b2c3….zip","sha256":"…"}
<base>/docs-a1b2c3….zip       every doc, every locale, one object
```

Each server reads **only the pointer**, on a short TTL — one small request — and fetches the
artifact when the sha moves. The new version is unpacked beside the old one and swapped in by
renaming a symlink, so a request sees one whole version or the other, never a tree mid-write.

## Turning it on

Set one config key. In the `config` tier it is stored once and every server in the fleet picks it
up, which is the point — there is no per-server file to edit and no server to log into.

| Key | | |
|---|---|---|
| `tiger.docs.content_source.url` | — | base URL holding `current.json` and the artifacts. **This is the on switch.** |
| `tiger.docs.content_source.dir` | `<app>/var/docs-content` | where versions are unpacked |
| `tiger.docs.content_source.ttl` | `60` | seconds between pointer checks (floor: 5) |
| `tiger.docs.content_source.keep` | `3` | versions kept on disk for rollback |

Unset, nothing changes: the module reads its own `content/` directory, or whatever
`docs.content_dir` points at, exactly as before.

## What it guarantees

- **A new server is correct on its first request.** It resolves the pointer and installs before
  serving, rather than waiting for a scheduled copy to find it.
- **Publishing is atomic.** The pointer is written after the artifact, and the swap is a rename.
- **A rollback is one small file.** Repoint `current.json` at an earlier sha; servers already
  holding that version swap back without downloading anything.
- **A broken publish is refused.** The pointer carries the artifact's SHA-256 and a mismatch is
  not installed — the server keeps the version it has.
- **An unreachable origin does not take the docs down.** The last good version keeps serving and
  the fetch is retried on the next TTL.

## Knowing what a server is serving

Docs pages carry the version in their head:

```bash
curl -s https://example.com/docs | grep tiger-docs-version
```

This exists because the opposite is the expensive failure. A fleet can sit hours behind its
content while every page returns 200 and every server looks healthy; without a version in the
response, the first thing that notices is a person reading the site.

## Publishing side

Any process that can write two objects can publish. The order is the only rule — **artifact
first, pointer last** — so the pointer never names bytes that are not there yet. Give the artifact
a long cache lifetime (its name contains its sha, so it is immutable) and the pointer a short one,
or a CDN will hold a publish back.

`WebTigers/TigerDocs-content` does this from a GitHub Action, and verifies the result by fetching
both back over the public URL before calling the publish done.
