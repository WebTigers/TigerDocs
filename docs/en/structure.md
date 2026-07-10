<!-- tiger:doc
parent: authoring
order: 20
title: Collections, sections & nesting
-->

# Collections, sections & nesting

Three simple ideas assemble the whole nav.

## Collections = folders

A **collection** is the top-level entry in the docs dropdown. It comes from one of two places:

- A **subfolder** of the platform content (`content/<locale>/<collection>/`).
- A **module's `docs/` folder** (`<module>/docs/<locale>/`) — the module *is* the collection.

Either way, its `_index.md` sets the label + order + landing.

## Sections = header nodes

Group pages under a labeled heading with a `header: true` node — a label with no page of its own:

```markdown
<!-- tiger:doc
header: true
order: 10
title: Getting started
-->
```

Pages join a section by pointing `parent:` at the header's **id** (its filename without `.md`).

## Nesting = `parent`, to any depth

`parent` links a node to its parent — and there's no depth limit. A page can be the child of another page, which is the child of a header, and so on. The sidebar renders the whole tree, indented.

```
getting-started        (header)
├── quick-start        (parent: getting-started)
└── concepts           (parent: getting-started)
    └── the-cascade    (parent: concepts)   ← nests as deep as you like
```

## Ordering

`order` sorts siblings — decimals let you insert between existing items without renumbering, and `first`/`last` are sugar. A section's position is just its header's `order`; a page's position is its own `order` within its parent.
