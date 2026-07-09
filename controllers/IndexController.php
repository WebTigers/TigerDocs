<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Docs_IndexController — the public documentation surface, in the theme's PUBLIC layout
 * (SSR: the server renders the page body, the browser just displays it).
 *
 * The docs live at the CANONICAL route `docs/index/docs` (Docs_IndexController::docsAction).
 * The pretty public path `/docs` (and `/docs/<slug>`) is an OPTIONAL override declared in
 * Docs_Bootstrap and applied by Tiger_Controller_Plugin_RouteOverride — it maps here, handing
 * the remaining path in as `slug`. See ROUTING.md.
 *
 *   docs/index/docs             -> landing (intro + section tree)
 *   docs/index/docs + slug=...  -> one doc, resolved dual-source (DB then file)
 *
 * All resolution + rendering lives in Docs_Model_Docs; this controller only reads and renders
 * (thin controller). Public in configs/acl.ini.
 */
class Docs_IndexController extends Tiger_Controller_Action
{
    /** @var Docs_Model_Docs */
    protected $_docs;

    public function init()
    {
        parent::init();
        $this->_docs = new Docs_Model_Docs();
    }

    /** The module's default action just forwards to the canonical docs viewer. */
    public function indexAction()
    {
        $this->_forward('docs');
    }

    /** The docs viewer: landing when there's no slug, otherwise one resolved doc (404 on miss). */
    public function docsAction()
    {
        $locale = $this->_locale();
        $slug   = (string) $this->getParam('slug', '');
        $this->view->tree = $this->_docs->tree($locale);

        if ($slug === '') {
            $this->view->title   = 'Documentation';
            $this->view->docSlug = '';
            $this->_helper->viewRenderer->setNoRender(true);
            $this->renderScript('index/index.phtml');
            return;
        }

        $doc = $this->_docs->resolve($slug, $locale);
        if (!$doc) {
            throw new Zend_Controller_Action_Exception('Doc not found', 404);
        }
        $this->view->title   = $doc['title'] . ' — Documentation';
        $this->view->doc     = $doc;
        $this->view->docSlug = $doc['slug'];
        $this->_helper->viewRenderer->setNoRender(true);
        $this->renderScript('index/view.phtml');
    }

    /** Current request locale (set by the LocalePrefix plugin), defaulting to English. */
    protected function _locale()
    {
        return defined('LANG') ? LANG : 'en';
    }
}
