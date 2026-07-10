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

    /**
     * Boot the base controller, then instantiate the docs engine for the actions.
     *
     * @return void
     */
    public function init()
    {
        parent::init();
        $this->_docs = new Docs_Model_Docs();
    }

    /**
     * The module's default action just forwards to the canonical docs viewer.
     *
     * @return void
     */
    public function indexAction()
    {
        $this->_forward('docs');
    }

    /**
     * The docs viewer. The route hands us the path after /docs as `slug`; its leading segment
     * selects the COLLECTION (Guide is default + prefix-less, others are namespaced):
     *   /docs                     → guide landing (_index)
     *   /docs/first-module        → guide/first-module
     *   /docs/admin               → admin landing
     *   /docs/admin/pages         → admin/pages
     * An empty doc slug renders the collection's landing; otherwise one resolved doc (404 on miss).
     *
     * @return void
     * @throws Zend_Controller_Action_Exception (404) when the requested doc slug resolves to nothing
     */
    public function docsAction()
    {
        $locale = $this->_locale();
        $base   = $this->_base();
        $raw    = trim((string) $this->getParam('slug', ''), '/');

        // Public surface → only PUBLIC-visibility docs. Split the leading segment: if it names a
        // (public) collection, it's <collection>/<rest>, else the default (guide).
        $vis        = Docs_Model_Docs::VIS_PUBLIC;
        $collection = Docs_Model_Docs::DEFAULT_COLLECTION;
        $docSlug    = $raw;
        if ($raw !== '') {
            $seg = explode('/', $raw, 2);
            if (in_array($seg[0], $this->_docs->collectionSlugs($locale, $vis), true)) {
                $collection = $seg[0];
                $docSlug    = $seg[1] ?? '';
            }
        }

        $this->view->collections = $this->_docs->collections($locale, $vis);   // the dropdown
        $this->view->collection  = $collection;                                // the active one
        $this->view->tree        = $this->_docs->tree($locale, $collection, $base, $vis, Docs_Model_Docs::DEFAULT_COLLECTION);
        $this->view->docsBase    = $base;

        $doc = $this->_docs->resolve($docSlug, $locale, '', $collection, $vis);

        if ($docSlug === '') {
            // Collection landing — its _index (may be null → the view shows a generic header).
            $this->view->title   = ($doc['title'] ?? 'Documentation') . ' — Documentation';
            $this->view->doc     = $doc;
            $this->view->docSlug = '';
            $this->_helper->viewRenderer->setNoRender(true);
            $this->renderScript('index/index.phtml');
            return;
        }

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

    /**
     * The docs' active public base path — the effective override prefix (e.g. /docs, or /help
     * if an admin retargeted it), so every in-page link follows the configured route instead of
     * a hardcoded /docs. Falls back to /docs when nothing is declared.
     */
    protected function _base()
    {
        $o = Tiger_Routing_Overrides::get('docs');
        $prefix = ($o && $o['prefix'] !== '') ? $o['prefix'] : 'docs';
        return '/' . $prefix;
    }
}
