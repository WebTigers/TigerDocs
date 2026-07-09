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
 */
class Docs_AdminController extends Tiger_Controller_Action
{
    public function init()
    {
        parent::init();
        $this->_helper->layout()->setLayout('admin');
    }

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
}
