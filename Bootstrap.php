<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Docs module bootstrap.
 *
 * A first-party, INSTALLABLE Tiger module (the first canary for the Module Installer): it
 * ships in its own public repo (WebTigers/TigerDocs) and installs into
 * application/modules/docs/. Purely additive — auto-discovered by ZF1's module scan, it
 * registers a public /docs surface without touching any core file.
 *
 * Extending Zend_Application_Module_Bootstrap gives the module its resource autoloader, so
 * Docs_Model_* (models/) + Docs_Service_* (services/) load by convention; controllers load
 * via the registered module dir; configs/acl.ini + languages/ are picked up by the core globs.
 */
class Docs_Bootstrap extends Zend_Application_Module_Bootstrap
{
    /**
     * Register the nested-slug route: /docs/<slug> (slug may contain slashes, e.g.
     * guides/install) -> Docs_IndexController::viewAction with a `slug` param.
     *
     * A plain /docs (no slug) is left to the default module route (module=docs ->
     * index/index = the landing). The regex needs ≥1 char after "docs/", so it can't
     * swallow the bare /docs. Added here (after the router resource is up) so it's
     * matched before the generic :module/:controller/:action route.
     */
    protected function _initDocsRoutes()
    {
        $router = Zend_Controller_Front::getInstance()->getRouter();
        $router->addRoute('docs_view', new Zend_Controller_Router_Route_Regex(
            'docs/(.+)',
            ['module' => 'docs', 'controller' => 'index', 'action' => 'view'],
            [1 => 'slug'],
            'docs/%s'
        ));
    }
}
