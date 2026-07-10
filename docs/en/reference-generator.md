<!-- tiger:doc
parent: authoring
order: 30
title: Generating API reference
-->

# Generating API reference

You don't hand-write API reference pages — and you don't commit them. Reference is a **build
artifact**: the generator reads each class's **docblocks and method signatures** and emits one
`tiger:doc`-annotated markdown page per class, regenerated on the instance at every deploy. Those
pages are ordinary docs — the same scan, cache, nav, and ⌘K search pick them up — but they live
only on the running instance, never in a repo. Reference *is* docs.

It's a token-based reader — it never boots the app or autoloads your classes — so you (or an AI
agent building a module) can point it at any source tree and it just works.

## The build hook

The one command you actually run — on deploy, or after installing a module — rebuilds **all**
reference for the instance:

```bash
php application/modules/docs/bin/build-reference.php
```

It writes into `<app>/var/docs-generated/<locale>/` — a **gitignored** build area:

- **platform** (`Tiger_*`) → `var/docs-generated/en/reference/` (the **Reference** collection)
- **each module** → `var/docs-generated/en/<slug>/`, which the engine **merges** into that
  module's own collection as a **Reference** section, right alongside its hand-written docs.

Then warm the index (visit `/docs`, or hit **Rebuild index** in the admin) so the pages show.

## Why it's never committed

Generated pages rot the moment the code changes, and they blur source vs. artifact. So they go to
the gitignored `var/docs-generated/` — **not** your module's `docs/` folder, and **not** the
content repo. Rebuilding from code on every deploy means the reference is always in sync, and both
repos stay clean. (Building docs *on the instance* is the supported path; committing generated
files is not.)

## Under the hood

The hook calls a single-target generator you can run directly while developing a module — handy
to preview your reference before shipping:

```bash
php application/modules/docs/bin/reference.php <module-dir> --out=<app>/var/docs-generated/en/<slug>
```

Options: `--out=DIR`, `--locale=en`, `--section=Name` (default "Reference"), `--order=900`.

## What gets documented

The generator is deliberately selective — reference is a **contract**, not a code dump:

- **Only `@api` classes.** A class must carry `@api` in its class docblock. `@internal`
  classes (and anything untagged) are skipped, so private plumbing never leaks into public docs.
- **Only public methods.** Protected/private methods and `_`-prefixed helpers are omitted
  (`__construct` is kept).
- **Descriptions come from your docblocks.** Summary, description, `@param`, `@return`, and
  `@throws` render straight through. Types in the signature win; the docblock fills in the prose.
- **Hand-written always wins.** If a generated page and a hand-written page share an id, yours
  is served — generated pages only ever *add*.

This is why the docblock contract matters: a well-kept docblock *is* the reference.
