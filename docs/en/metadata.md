<!-- tiger:doc
parent: authoring
order: 10
title: The tiger:doc block
-->

# The tiger:doc block

Every file self-describes in a **single HTML comment** at the top — invisible when rendered, trivial to scan, and impossible to break the page with:

```markdown
<!-- tiger:doc
parent:     getting-started
order:      20
title:      Add docs to a module
header:     true
visibility: public
-->
```

## The fields

| Field | What it does | Default |
|---|---|---|
| `title` | The display title in the nav + on the page | the first `# H1`, else the humanized filename |
| `order` | Sort weight among siblings — a float, so `5.22` slots between `5` and `6`; also `first` / `last` | `last` |
| `parent` | The **id** (filename without extension) of the parent node → nests to any depth | top level |
| `header` | `true` marks a **label-only** node (a section heading with no page of its own) | — |
| `visibility` | `public` or `admin` — which surface the doc appears on | the collection's `_index` visibility, else `public` |

## Everything is optional

A bare `.md` file with **no block at all** still shows up — ungrouped, sorted last. The metadata is pure refinement; add only what you need.

## The `_index.md` file

`_index.md` is special: it isn't a page, it configures the **collection** itself. Its `title` is the dropdown label (translate it per locale), its `order` positions the collection, its `visibility` sets the default for every page in the folder, and its body is the collection's landing page.
