# AGENTS.md — building docs in Tiger

Instructions for an AI assistant (or a new human contributor) authoring or extending
documentation in a Tiger app. This is the **docs module** (`Docs_*`); for platform conventions
read [tiger-core/AGENTS.md](https://github.com/WebTigers/tiger-core/blob/main/AGENTS.md), and in
particular its **"Docblocks — the reference contract"** section — the [reference
generator](#reference-generator) turns those docblocks into pages, so that contract *is* the
API-reference authoring spec.

> Tiger is designed to be read and written largely by AI. Docs live as files in the code, in the
> same repo as what they document. Match the surrounding style.

## The one thing to know: a doc is a file

There is **no manifest, no registration, no build step to author**. A doc is a markdown file in
a content directory. Drop the file in, it's a doc. Delete it, it's gone. The engine
(`Docs_Model_Docs`) scans directories on demand and caches the result per server. To write docs
you never touch PHP — you add markdown files with a small metadata header.

**Extend, don't edit.** On install this repo becomes `application/modules/docs/` and is
Tiger-owned (replaced on update). To *add* docs you drop content files (see below) or use the
[DB override tier](#db-override) — you do **not** edit `Docs_Model_*` or the views.

## Where docs live

Two kinds of source are aggregated automatically — you don't wire them together:

```
content/<locale>/<collection>/*.md        # 1. PLATFORM content shipped in THIS module
<any-module>/docs/<locale>/*.md           # 2. MODULE docs — self-documenting modules
```

1. **Platform content** — each subfolder of this module's `content/<locale>/` is a collection
   (Guide, CMS, …). This is where the app's own product docs live.
2. **Module docs** — every **active** module that ships a `docs/<locale>/` folder becomes its
   own collection, slug = the module name. Drop a `docs/` folder in your module and it appears in
   the docs site; deactivate the module and it's gone. (Global toggle: `docs.modules.scan`.)

`<locale>` is a **language-only** code (`en`, `es`) — like the rest of Tiger. English (`en`) is
the fallback; a missing localized file falls back to `en`.

## Authoring a doc: the `tiger:doc` block

Every file self-describes in a **single leading HTML comment** — invisible when rendered,
impossible to break the page with. Every key is optional:

```markdown
<!-- tiger:doc
parent:     getting-started    # id (filename) of the parent node → arbitrary-depth tree
order:      20                 # float among siblings; 5.22 slots between 5 and 6; also: first | last
title:      Add docs to a module   # else the first "# H1", else the humanized filename
header:     true               # a label-only node (a section heading; no page of its own)
visibility: public             # public (default) | admin — which SURFACE it appears on
-->

# Add docs to a module

Body is Markdown (or HTML, or trusted PHTML — see Rendering).
```

- **`id`** of a node = its filename without `.md` (`getting-started.md` → `getting-started`).
- **`parent`** points at another node's id → nesting runs **arbitrarily deep**, not one level.
- **`order`** is a float, so you can insert between siblings without renumbering; `first`/`last`
  pin to the ends.
- **`header: true`** makes a **section** — a label in the nav with children under it, no page.
  Give the section a `title` and `order`; its children set `parent:` to the section's id.

### Collections, landings & the default

- A **collection** is just a directory of these files. Its label/order/landing come from an
  **`_index.md`** in the folder (its `title`, `order`, and body are the collection's landing page).
- **Guide** is the default, **prefix-less** public collection: `/docs` → the guide landing,
  `/docs/first-module` → `guide/first-module`. Every other collection is namespaced:
  `/docs/<collection>/<slug>`.
- **Don't name a collection `admin`** — it collides with `Docs_AdminController` (the admin
  screens live under `/docs/admin/*`). The platform's admin-facing collection is `backoffice`.

## Visibility: two surfaces, one engine <a id="visibility"></a>

Every doc has a `visibility` — `public` (default) or `admin` — and that splits the same engine
into two surfaces:

| Surface | URL | Audience | Shows |
|---|---|---|---|
| **Public site** | `/docs` | guests + users (public layout) | `visibility: public` docs |
| **Admin help center** | `/docs/admin/help` | admins (admin shell) | `visibility: admin` docs |

Public docs are *what the product does*; admin docs are *how to operate a module*. A file's
visibility defaults to its collection's `_index` visibility, which defaults to `public`. The
engine never leaks across surfaces — a public URL cannot resolve an admin doc, and vice-versa
(`resolve()` returns null → 404).

So a self-documenting module typically ships **both**: public docs in `docs/en/` for its users,
and a few `visibility: admin` pages for whoever configures it.

## Reference generator <a id="reference-generator"></a>

You **don't hand-write API reference, and you don't commit it** — it's a **build artifact**,
regenerated on the instance from source at every deploy. `bin/reference.php` is a **token-based**
reader (no app boot, no autoload) that turns docblocks + signatures into `tiger:doc` pages; those
land in a **gitignored** area (`<app>/var/docs-generated/<locale>/`) that the engine scans as an
extra source — merging a **Reference** section into a module's own collection, or standing up the
platform `reference` collection. Reference *is* docs, but generated docs live only on the instance.

**The build hook** — run it after a deploy / module install (it rebuilds *everything*):

```bash
php application/modules/docs/bin/build-reference.php   # → <app>/var/docs-generated/en/
#   platform (Tiger_*)  → var/docs-generated/en/reference/   (the "Reference" collection)
#   each app module     → var/docs-generated/en/<slug>/       (a Reference section in its docs)
```

Then warm the index (`curl /docs`, or the admin **Rebuild index**). Under the hood the hook calls
the single-target generator, which you can also run directly while developing a module:

```bash
php application/modules/docs/bin/reference.php <module-dir> --out=<app>/var/docs-generated/en/<slug>
php application/modules/docs/bin/reference.php --platform=<tiger-lib> --out=<app>/var/docs-generated/en/reference
```

- Documents **only `@api` classes** and **only public methods** (`_`-prefixed + non-public are
  skipped; `__construct` kept). `@internal` classes are excluded.
- Summaries, `@param`, `@return`, `@throws` come **straight from the docblocks** — which is why
  the [docblock contract](https://github.com/WebTigers/tiger-core/blob/main/AGENTS.md) matters.
  A well-kept docblock is a good reference page; a bad one is a bad page.
- **Never commit the output** — not to the module repo, not to the content repo. It carries
  `generated: reference` in its `tiger:doc` block, targets the gitignored `var/docs-generated/`,
  and is rebuilt from code each deploy (so it can't rot). Hand-written pages always win a same-id
  collision. Building docs *on the instance* is the sanctioned path; committing generated files is not.

## The DB override tier <a id="db-override"></a>

Files are the version-controlled baseline. The **database is the last-wins override**: a `page`
row of `type = doc` with the same slug adds or replaces a doc on a **live install without
shipping a release** (an org-scoped row beats a global one; DB beats file). Both sources render
through the same engine. Reach for this when a tenant needs a one-off doc — not as the default
authoring path (files are the default; they're reviewable and travel with the code).

## The build cache (distributed-safe)

`Docs_Model_Index` fronts the scan with a **per-server, fingerprint-invalidated cache** — no DB,
no coordination, works in a fleet:

- The change signal is a cheap **fingerprint** — md5 of every content file's `path|mtime|size`
  (stat only, no reads). Add/edit/remove any file → the hash flips → that server rebuilds itself.
- The built index is serialized to `var/cache/tiger-docs/` (opcache-friendly PHP). It is a
  **regenerable artifact — never commit it; it's safe to delete**.
- Knobs (`tiger.docs.cache.*`): `enabled`, `check` (0 = "trust the deploy", skip the stat walk),
  `ttl` (throttle the walk), `dir` (override location).
- Force a rebuild: the admin **Settings → Docs → Rebuild index**, or `(new Docs_Model_Docs)->rebuildIndex($locale)`.

If you add or regenerate content on the box, clear `var/cache/tiger-docs` (or let the fingerprint
catch it on the next request).

## The engine API (thin controllers, all logic in the model)

Controllers only read + render. Everything lives in **`Docs_Model_Docs`** (`@api`):

| Method | Returns |
|---|---|
| `collections($locale, $vis)` | the dropdown: `[ ['slug','title','order','vis'], … ]` |
| `collectionSlugs($locale, $vis)` | collection slugs (for the URL split) |
| `tree($locale, $collection, $base, $vis, $default)` | the nested, url-stamped nav tree |
| `resolve($slug, $locale, $orgId, $collection, $vis)` | one doc `['slug','title','html','headings',…]` or null (→404) |
| `search($q, $locale, $vis, $limit)` | ranked hits |
| `rebuildIndex($locale)` | force a per-server rebuild |

Constants: `DEFAULT_COLLECTION = 'guide'`, `VIS_PUBLIC = 'public'`, `VIS_ADMIN = 'admin'`.

**Routing** (see [ROUTING.md](https://github.com/WebTigers/tiger-core/blob/main/ROUTING.md)):
the canonical route is `docs/index/docs`; the pretty `/docs` prefix is a **route override**
declared in `Docs_Bootstrap` (retargetable from admin Settings, e.g. to `/help`). Admin surfaces:
`/docs/admin/settings` (config) and `/docs/admin/help` (the help center, registered in the admin
sidebar via `Tiger_Admin_Nav`). Never hardcode `/docs` in a view — use the `$base` the controller
passes in.

**Rendering** is `Tiger_Cms_Renderer` — **Markdown** (default), **HTML**, or **trusted PHTML**.
Untrusted content is Markdown; PHTML is for platform-shipped files only.

**Search** is `Docs_Service_Search` over `/api` (`{ module:'docs', service:'search', method:'query',
q:'…', scope:'public'|'admin' }`), powering the ⌘K launcher on both surfaces. `scope=admin` is
admin-gated; it scores over the cached plain text, so it's cheap.

## Do / Don't

- **Do** add docs by dropping `.md` files with a `tiger:doc` block. **Don't** edit `Docs_Model_*`
  or add a manifest — there isn't one.
- **Do** ship a module's docs in `<module>/docs/<locale>/`, and generate its reference into the
  same place. **Don't** hand-write API reference — generate it.
- **Do** use `visibility: admin` for operator docs. **Don't** name a collection `admin`.
- **Do** keep `order` as floats so inserts don't renumber. **Do** set a `_index.md` per collection.
- **Don't** commit `var/cache/tiger-docs`. **Don't** hardcode the `/docs` prefix in views.
- **Do** keep docblocks clean — for `@api` classes they become the public reference.
