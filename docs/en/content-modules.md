<!-- tiger:doc
parent: administration
order: 40
title: Where the manual comes from
-->

# Where the manual comes from

TigerDocs is the **engine**. The manual it renders is **content**, and content belongs in its own
module.

A module that declares `provides.content` in its manifest supplies documentation — every
collection, every locale — and TigerDocs renders it ahead of the content it ships itself:

```json
{
    "slug": "manual",
    "provides": { "content": "content" }
}
```

```
content/
  en/guide/…  en/cms/…  en/backoffice/…
  de/guide/…  es/guide/…  fr/guide/…  …
```

That module carries no PHP, no routes and no migrations. It is the manual, under version control,
released and updated like anything else in Tiger.

## Why this and not a sync

Content used to be copied onto each server from outside Tiger — a bucket, a scheduled pull. That
works until you have more than one server. A machine that launched after the last copy serves
whatever it was built with; a copier that stops is invisible, because nothing reports what version
a server holds; and nothing ties the content a server is serving to anything you can name.

As a module, the manual inherits what every other module already has: a version, a release, a
registry row, update checks, auto-update opt-in, and a rollback that is just installing the
previous release. One release updates every install — your fleet, and anyone else running Tiger.

## Precedence

Content roots are resolved highest-priority first, and they are **additive**:

1. `docs.content_dir` — a directory named outright in config. The deliberate local override.
2. Active modules declaring `provides.content`, ordered by slug.
3. The docs module's own `content/` — the shipped default a bare install renders from.

A collection present in more than one root **merges**, and the higher-priority root wins a page
with the same id. So a content module can replace the whole manual, add one collection to it, or
override a single page, and TigerDocs does not need to know which. Deactivate the module and its
content is gone, exactly like a module's own `docs/` folder.

A locale falls back to `en` within each root, so a partially translated manual shows translated
pages where they exist and English everywhere else.

## Knowing what is being served

Docs pages carry the content module and its version in their head:

```bash
curl -s https://example.com/docs | grep tiger-docs-version
```

A fleet can sit a long way behind its manual while every server returns 200 and looks healthy.
This is what makes "which version is this box serving" answerable without logging into it.
