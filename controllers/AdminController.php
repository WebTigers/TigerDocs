<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Docs_AdminController — the Docs module's admin screen, in the PUMA admin shell.
 *
 * Reached from the sidebar's Settings › Docs (registered via Tiger_Admin_Settings in
 * Docs_Bootstrap) at /docs/admin/settings. Thin: it renders the form pre-filled from the live
 * route-override config; saving is an /api call (Docs_Service_Settings). ACL-gated admin+
 * (configs/acl.ini). Because it's a real controller, the pretty /docs route override never
 * shadows it (RouteOverride only claims URLs nothing dispatches — see ROUTING.md).
 *
 * Extends Tiger_Controller_Admin_Action, so it renders in the admin shell automatically (no
 * hand-set layout). Build admin screens to the ADMIN.md template.
 */
class Docs_AdminController extends Tiger_Controller_Admin_Action
{
    /**
     * Admin shell (layout) comes from the base; keep the explicit init cascade.
     *
     * @return void
     */
    public function init()
    {
        parent::init();
    }

    /**
     * Render the Docs settings screen, pre-filled from the live route-override config.
     *
     * @return void
     */
    public function settingsAction()
    {
        // The effective override (module default merged under any config override), enabled or not.
        $o = Tiger_Routing_Overrides::get('docs') ?: [
            'pattern' => 'docs', 'priority' => 100, 'enabled' => true,
        ];

        $form = new Docs_Form_Settings();
        $form->populate([
            'route_enabled'  => !empty($o['enabled']) ? 1 : 0,
            'route_pattern'  => (string) ($o['pattern'] ?: 'docs'),
            'route_priority' => (int) ($o['priority'] ?? 100),
        ]);

        $this->view->title    = 'Docs Settings — Tiger Admin';
        $this->view->form     = $form;
        $this->view->override = $o;
    }

    /**
     * The admin HELP CENTER — admin-visibility docs (how to operate each module), aggregated from
     * every active module + the platform via the same engine as /docs, filtered to visibility=admin
     * and rendered in the admin shell. Route: /docs/admin/help(/collection/slug) (see Docs_Bootstrap).
     *
     * @return void
     * @throws Zend_Controller_Action_Exception (404) when a doc slug is given but resolves to nothing
     */
    public function helpAction()
    {
        $docs   = new Docs_Model_Docs();
        $locale = defined('LANG') ? LANG : 'en';
        $vis    = Docs_Model_Docs::VIS_ADMIN;
        $base   = '/docs/admin/help';
        $raw    = trim((string) $this->getParam('docpath', ''), '/');

        // Leading segment selects the collection (all namespaced in admin — no prefix-less default).
        $slugs      = $docs->collectionSlugs($locale, $vis);
        $collection = '';
        $docSlug    = '';
        if ($raw !== '') {
            $seg = explode('/', $raw, 2);
            if (in_array($seg[0], $slugs, true)) {
                $collection = $seg[0];
                $docSlug    = $seg[1] ?? '';
            }
        }
        if ($collection === '') {
            $collection = $slugs[0] ?? '';   // land on the first admin collection
        }

        $this->view->collections = $docs->collections($locale, $vis);
        $this->view->collection  = $collection;
        $this->view->docsBase    = $base;
        $this->view->docScope    = $vis;
        $this->view->tree        = $collection !== '' ? $docs->tree($locale, $collection, $base, $vis, '') : [];
        $this->view->title       = 'Help — Tiger Admin';

        $doc = ($collection !== '') ? $docs->resolve($docSlug, $locale, '', $collection, $vis) : null;
        if ($docSlug !== '' && !$doc) {
            throw new Zend_Controller_Action_Exception('Doc not found', 404);
        }
        $this->view->doc     = $doc;
        $this->view->docSlug = $doc['slug'] ?? '';
    }
}
