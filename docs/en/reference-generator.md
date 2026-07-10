<!-- tiger:doc
parent: authoring
order: 30
title: Generating API reference
-->

# Generating API reference

You don't hand-write API reference pages — you generate them from the source. The generator
reads each class's **docblocks and method signatures** and emits one `tiger:doc`-annotated
markdown page per class. Those pages are ordinary docs: the same scan, cache, nav, and ⌘K
search pick them up with no extra wiring. Reference *is* docs.

It's a token-based reader — it never boots the app or autoloads your classes — so you (or an AI
agent building a module) can point it at any source tree and it just works.

## Ship reference with your module

From the docs module's `bin/`, target another module's directory:

```bash
php application/modules/docs/bin/reference.php application/modules/billing
```

That scans `billing/`'s `services/`, `models/`, `forms/`, and `library/`, and writes a
**Reference** section into `billing/docs/en/` — one page per class, alphabetical. Commit those
files and the reference ships *with the module*. Re-run it any time; it's idempotent (it removes
its own previously-generated pages first, and never touches your hand-written ones).

Options:

```
--out=DIR        write elsewhere (default: <module>/docs/<locale>)
--locale=en      target locale folder
--section=Name   section heading (default: "Reference")
--order=900      section order (default: 900 — after hand-written sections)
```

## What gets documented

The generator is deliberately selective — reference is a **contract**, not a code dump:

- **Only `@api` classes.** A class must carry `@api` in its class docblock. `@internal`
  classes (and anything untagged) are skipped, so private plumbing never leaks into public docs.
- **Only public methods.** Protected/private methods and `_`-prefixed helpers are omitted
  (`__construct` is kept).
- **Descriptions come from your docblocks.** Summary, description, `@param`, `@return`, and
  `@throws` render straight through. Types in the signature win; the docblock fills in the prose.

This is why the [docblock contract](/docs) matters: a well-kept docblock *is* the reference.

## Platform reference

The same tool documents the framework itself — every `Tiger_*` `@api` class — sectioned by
namespace (`Tiger_Model_*` → **Model**, `Tiger_Service_*` → **Service**, …):

```bash
php application/modules/docs/bin/reference.php \
    --platform=vendor/webtigers/tiger-core/library/Tiger \
    --out=path/to/content/en/reference
```
