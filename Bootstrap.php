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
