<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Docs module bootstrap.
 *
 * A first-party, INSTALLABLE Tiger module (the first canary for the Module Installer): it
 * ships in its own public repo (WebTigers/TigerDocs) and installs into
 * application/modules/docs/. Purely additive — auto-discovered by ZF1's module scan, it
 * registers a public docs surface without touching any core file.
 *
 * Extending Zend_Application_Module_Bootstrap gives the module its resource autoloader, so
 * Docs_Model_* (models/) + Docs_Service_* (services/) load by convention; controllers load
 * via the registered module dir; configs/acl.ini + languages/ are picked up by the core globs.
 */
class Docs_Bootstrap extends Zend_Application_Module_Bootstrap
{
    /**
     * Declare the pretty public route: `/docs` -> the canonical `docs/index/docs`, with any
     * remaining path handed in as `slug` (so /docs/guides/deploy works). This is a DECLARATION,
     * not a route add — Tiger_Controller_Plugin_RouteOverride applies it, only for URLs no real
     * controller handles (so /docs/admin/settings is never shadowed). The admin can retarget,
     * disable, or reprioritize it via the config tier (tiger.routing.override.docs.*) from the
     * settings screen. See ROUTING.md.
     */
    protected function _initRouteOverride()
    {
        Tiger_Routing_Overrides::register('docs', [
            'pattern'  => 'docs',
            'target'   => 'docs/index/docs',
            'priority' => 100,
        ]);
    }

    /**
     * Contribute every PUBLIC doc URL to the sitemap. TigerDocs owns its `/docs/…` URL shape, so it
     * maps itself into the core Tiger_Sitemap registry (like the CMS pages + blog providers) rather
     * than the SEO layer learning what a docs URL looks like. Guarded so the module still installs on
     * a Core that predates the registry — it simply contributes nothing there.
     */
    protected function _initDocsSitemap()
    {
        if (!class_exists('Tiger_Sitemap')) {
            return;   // a Core too old for the sitemap registry — nothing to contribute to
        }
        Tiger_Sitemap::register('docs', function (array $ctx) {
            try {
                return (new Docs_Model_Docs())->publicUrls((string) ($ctx['locale'] ?? 'en'));
            } catch (Throwable $e) {
                return [];   // a docs build hiccup must never break the sitemap
            }
        });
    }

    /**
     * Keep this server's copy of the remote content current.
     *
     * The job is registered unconditionally but is a no-op when `docs.content_url` is unset, so an
     * install with no remote source pays nothing for it. Every 15 minutes, because the point of
     * publishing content to one path is that a correction reaches the fleet without a deploy — and
     * an hour is long enough to be indistinguishable from "it didn't work". The fetch is one HTTP
     * request when nothing has changed, so the frequency is close to free.
     *
     * Guarded: on a Core that predates the scheduler the content still resolves, it just needs the
     * admin button or the deploy hook to refresh.
     */
    protected function _initDocsContentFetch()
    {
        if (!class_exists('Tiger_Schedule')) {
            return;
        }
        Tiger_Schedule::register([
            'key'           => 'docs.content_fetch',
            'label'         => 'Fetch docs content',
            'every'         => Tiger_Schedule::EVERY_15,
            'run'           => 'Docs_Model_Remote::syncJob',
            'email_on_fail' => false,
        ]);
    }

    /** Contribute the Docs page to the admin Settings tree (ACL-gated in the menu). */
    protected function _initAdminSettings()
    {
        Tiger_Admin_Settings::register([
            'key'      => 'docs',
            'label'    => 'Docs',
            'icon'     => 'fa-book',
            'href'     => '/docs/admin/settings',
            'resource' => 'Docs_AdminController',
            'order'    => 60,
        ]);
    }

    /**
     * The in-admin HELP CENTER — the admin-visibility docs surface (how to operate each installed
     * module), aggregated from every module's docs/ folder. A genuinely custom URL shape (a nested
     * path under an admin action), so it's a real regex route, not a pretty-alias override:
     * `/docs/admin/help(/collection/slug)` → Docs_AdminController::helpAction with the remainder as
     * `docpath`. It doesn't shadow /docs/admin/settings (different prefix).
     */
    protected function _initAdminHelpRoute()
    {
        try {
            Zend_Controller_Front::getInstance()->getRouter()->addRoute('docs_admin_help',
                new Zend_Controller_Router_Route_Regex(
                    'docs/admin/help(?:/(.+))?',
                    ['module' => 'docs', 'controller' => 'admin', 'action' => 'help'],
                    [1 => 'docpath'],
                    'docs/admin/help/%s'
                ));
        } catch (Throwable $e) {
            // Router not ready → skip; the canonical docs/admin/help path still dispatches.
        }
    }

    /**
     * Register a top-level "Help" item in the admin sidebar (via the Tiger_Admin_Nav registry —
     * guarded so the module still works on a Core that predates it; the surface is reachable by URL
     * regardless). ACL-gated to Docs_AdminController, so it hides for non-admins.
     */
    protected function _initAdminHelpNav()
    {
        if (class_exists('Tiger_Admin_Nav')) {
            Tiger_Admin_Nav::register([
                'key'      => 'docs_help',
                'label'    => 'Help',
                'icon'     => 'fa-circle-question',
                'href'     => '/docs/admin/help',
                'match'    => '/docs/admin/help',
                'resource' => 'Docs_AdminController',
                'order'    => 90,
            ]);
        }
    }
}
