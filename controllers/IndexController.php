<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Docs_IndexController — the public documentation surface, rendered in the theme's
 * PUBLIC layout (SSR: the server renders the page body, the browser just displays it).
 *
 *   /docs             -> indexAction : the docs landing (intro + section tree)
 *   /docs/<slug...>   -> viewAction  : one doc, resolved dual-source (DB then file)
 *
 * The slug route is registered by Docs_Bootstrap::_initDocsRoutes. All resolution +
 * rendering lives in Docs_Model_Docs; this controller only reads and hands off to the
 * view (thin controller). Public in configs/acl.ini.
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

    /** The docs home: welcome + the section/link tree from the content manifest. */
    public function indexAction()
    {
        $this->view->title   = 'Documentation';
        $this->view->tree    = $this->_docs->tree($this->_locale());
        $this->view->docSlug = '';
    }

    /** A single doc. Resolves DB-then-file; a miss is a clean 404 (ErrorController). */
    public function viewAction()
    {
        $doc = $this->_docs->resolve((string) $this->getParam('slug', ''), $this->_locale());
        if (!$doc) {
            throw new Zend_Controller_Action_Exception('Doc not found', 404);
        }

        $this->view->title   = $doc['title'] . ' — Documentation';
        $this->view->doc     = $doc;
        $this->view->tree    = $this->_docs->tree($this->_locale());
        $this->view->docSlug = $doc['slug'];
    }

    /** Current request locale (set by the LocalePrefix plugin), defaulting to English. */
    protected function _locale()
    {
        return defined('LANG') ? LANG : 'en';
    }
}
