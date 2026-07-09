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
}
