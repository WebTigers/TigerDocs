<!-- tiger:doc
title: Writing module docs
order: 10
visibility: public
-->

# Writing module docs

Documentation in Tiger is **files, not config**. The content directory *is* the table of contents. Drop a markdown file in the right folder and it appears; delete it and it's gone; reorder by bumping a number. No manifest, ever.

## The `tiger:doc` block

Every file self-describes in a leading HTML comment — invisible when rendered, trivial to scan:

```markdown
<!-- tiger:doc
parent:     getting-started   # id of the parent node → nests to ANY depth (omit = top level)
order:      20                # float among siblings; 5.22 slots between 5 and 6; first | last
title:      Your first step   # optional; else the first # H1, then the humanized filename
header:     true              # optional; a label-only node (no page), just groups children
visibility: public            # public (default) | admin — which surface it shows on
-->

# Your first step
…markdown…
```

**None of it is required.** A bare `.md` with no block still shows up (ungrouped, sorted last). The metadata is pure refinement.

## Collections, sections, nesting

- A **subfolder** (for the platform) or a **module's `docs/`** folder is a **collection** — an entry in the top-level dropdown. Its `_index.md` sets the label (translatable) + order + landing body.
- **Sections** are `header: true` nodes; pages point at them with `parent:`.
- **Nesting** is arbitrary — a page can be the child of another page, as deep as you like.

## Visibility: two audiences, one folder

The `visibility` line splits your docs across two surfaces from a single `docs/` folder:

- **`public`** (default) — the public docs site at `/docs`. For your users.
- **`admin`** — the in-app **help center** in the admin shell. For operators: *how to configure and run this module.*

So a module ships `overview.md` (`public`) and `configure.md` (`admin`), and each lands where its audience will look.
