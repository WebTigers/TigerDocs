<?php
/**
 * Docs_IndexController — the public documentation surface (STUB).
 *
 * Renders in the active theme's PUBLIC layout. Routed at /docs by the default module route
 * (module=docs → Docs_IndexController). Public in configs/acl.ini. This stub renders a
 * landing page; the real docs store (a `docs` table of articles + search + nav tree) is the
 * next build — the point of this skeleton is to be a valid, installable module for the
 * Module Installer's first end-to-end test.
 */
class Docs_IndexController extends Tiger_Controller_Action
{
    public function indexAction()
    {
        $this->view->title = 'Documentation';
    }
}
