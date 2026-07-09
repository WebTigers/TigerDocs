# Introduction

Welcome to **Tiger Docs** — the documentation site that ships with your Tiger app. It renders
help pages in your active theme, at `/docs`, from two sources you can mix freely:

- **Files** shipped in this module (`content/en/*.md`) — the default. Version-controlled,
  reviewable in a pull request, and present on a fresh install with zero database rows.
- **Database** pages (a `page` row of `type = doc`) — the override tier. An install or a
  single tenant can add or replace a doc *without touching a file or shipping a release*.

When both exist for the same slug, **the database wins** — so the files are your shipped
baseline and the database is where per-install or per-tenant edits live. This is the same
"files are the base, the DB is the last-wins override" pattern Tiger uses for config and
translations, applied to content.

## Why it's built this way

Tiger ships **lean**: the framework does not bake a giant documentation set into every app.
The platform's own docs live as files at
[tiger.webtigers.com/docs](https://tiger.webtigers.com/docs) — the very files in this module's
`content/` directory. Your app inherits this module, writes its *own* product docs as files
(or DB pages), and gets a themed, linkable docs site for free.

## How a request resolves

1. You visit `/docs/getting-started`.
2. `Docs_Model_Docs` looks for a **DB** doc with that slug (org-scoped row beats global).
3. If there's no DB doc, it looks for a **file**: `content/<locale>/getting-started.md`,
   falling back to `content/en`.
4. The body — from either source — is rendered by `Tiger_Cms_Renderer` (Markdown, HTML, or
   trusted PHTML) and shown inside your theme's public layout.
5. No match in either source → a clean **404**.

Ready to add your own? See **[Writing docs — files & DB](/docs/writing-docs)**.
