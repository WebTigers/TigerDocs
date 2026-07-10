<!-- tiger:doc
parent: getting-started
order: 10
title: Add docs to a module
-->

# Add docs to a module

A module documents itself by shipping a `docs/` folder. If TigerDocs is installed, that module becomes its own section in the docs — **zero wiring**.

## 1. Create the folder

```
application/modules/billing/
└── docs/
    └── en/
        ├── _index.md      # this collection's dropdown label + order + landing
        └── overview.md    # a page
```

## 2. Write `_index.md`

The `_index` gives the collection its (translatable) label, its position in the dropdown, and a landing page:

```markdown
<!-- tiger:doc
title: Billing
order: 60
visibility: public
-->

# Billing

Take payments, manage plans, and reconcile invoices.
```

## 3. Write a page

Every other file is a page. It self-describes in a leading `tiger:doc` comment (all fields optional):

```markdown
<!-- tiger:doc
title: How invoicing works
order: 10
-->

# How invoicing works
…markdown…
```

## 4. That's it

Activate the module and the **Billing** section appears in the docs dropdown, its pages nested in the sidebar, its text in search. Deactivate the module and it vanishes. Nothing else to register.

Next: **[The tiger:doc block](/docs/docs/metadata)** covers every field.
