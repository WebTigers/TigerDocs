<!-- tiger:doc
parent: administration
order: 30
title: Where docs come from
visibility: admin
-->

# Where docs come from

Docs on this site are **aggregated** from two kinds of source. Knowing which is which tells you where to add or fix content.

## Platform docs

The core collections (Guide, CMS, Reference, and the admin back office) live in the platform's own content directory — for WebTigers, a private content repo deployed alongside the app. These are the framework's docs.

## Module docs

Every **active** module that ships a `<module>/docs/` folder contributes its own collection automatically — discovered on each server, reflecting exactly which modules are installed and on. Activate a module and its docs appear; deactivate it and they're gone. (Toggle the whole behavior with `docs.modules.scan`.)

## Adding docs

- **To a module** — drop files in that module's `docs/en/` folder (see [Add docs to a module](/docs/docs/quick-start)) and redeploy the module.
- **To the platform** — add files to the platform content directory and deploy.
- **Per-tenant, no deploy** — author a `page` of type `doc` in the CMS; it overrides the matching file for that org (see the public *Files & DB docs* page).

Either way, the index rebuilds itself the moment the files land.
