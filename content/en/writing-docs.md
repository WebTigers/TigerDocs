# Writing docs — files & DB

A doc can live as a **file** in this module or as a **database** page. You'll use files for
almost everything; the database is there for the cases files can't cover.

## Option 1 — a file (the default)

1. Create a file under `content/<locale>/`, named for its slug:

   ```
   content/en/guides/deploy.md      ->  /docs/guides/deploy
   ```

2. Add it to `content/manifest.json` so it appears in the sidebar and landing:

   ```json
   {
     "title": "Guides",
     "items": [
       { "slug": "guides/deploy", "title": "Deploying" }
     ]
   }
   ```

That's it. Commit the file, ship it, and the doc is live everywhere the module is installed.

### Supported formats

The file extension picks the renderer (all go through `Tiger_Cms_Renderer`):

| Extension | Format | Notes |
|---|---|---|
| `.md` | Markdown | The default. Safe. Its first `# H1` becomes the page title. |
| `.html` / `.htm` | HTML | Rendered as-is, then the `[shortcode]` pass. Safe. |
| `.phtml` | PHTML | Full view scope + helpers. **Trusted code** — for shipped files only. |

## Option 2 — a database doc (the override tier)

Reach for this when a doc must change **without a release** — an install-specific note, a page
a non-developer edits in the admin, or a doc that differs for one tenant.

A DB doc is just a `page` row with **`type = doc`** and a matching **`slug`**. It reuses the
CMS content store, so it gets org-scoping, publish gating, versioning, and redirects for free.
Because resolution checks the database first, **a DB doc with the same slug overrides the
shipped file** — and an **org-scoped** row overrides a **global** one, so a single tenant can
override a doc for just themselves.

> **Rule of thumb:** ship your docs as files (reviewable, versioned, present on install). Use a
> database doc only to override or add content on a live install without shipping code.

## Titles, nav, and 404s

- **Sidebar/landing titles** come from `manifest.json`.
- **A doc's page title** comes from its own content — the first Markdown `# H1`, or a DB doc's
  `title` column.
- A slug that matches **neither** a DB doc nor a file returns a clean **404**.

## A note on safety

Slugs are normalized to safe, lowercased path tokens — `..` and stray characters are rejected,
so `/docs/<slug>` can never escape the `content/` directory. Markdown and HTML are safe formats;
PHTML is trusted code and is intended for the files you ship, not for untrusted input.
