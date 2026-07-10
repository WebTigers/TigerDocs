<!-- tiger:doc
title: Documenting Modules
order: 50
visibility: public
-->

# Documenting Modules

Any Tiger module can document itself — **just drop a `docs/` folder in the module root.** If TigerDocs is installed, that module becomes its own section in the docs, with **zero wiring**. Deactivate the module and its docs disappear with it.

```
application/modules/billing/
└── docs/
    └── en/
        ├── _index.md        # this collection's dropdown label + order + landing
        ├── overview.md      # visibility: public  → shows on /docs
        └── configure.md     # visibility: admin   → shows in the admin help center
```

It's the same zero-config paradigm the platform docs use — a subfolder is a collection, and every file self-describes in a `<!-- tiger:doc -->` comment. Read **[Writing module docs](/docs/docs/writing-docs)** for the whole convention.
