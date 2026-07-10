<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Docs_Service_Settings — /api service for the Docs settings screen.
 *
 * Validates Docs_Form_Settings, then writes the public route override to the DB `config`
 * table (scope=global) via Tiger_Model_Config as tiger.routing.override.docs.* — the
 * live-override tier, effective next request, no deploy. Tiger_Routing_Overrides reads exactly
 * these keys. ACL: admin+ (configs/acl.ini).
 */
class Docs_Service_Settings extends Tiger_Service_Service
{
    public function save(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $form = new Docs_Form_Settings();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $v = $form->getValues();

        try {
            $cfg = new Tiger_Model_Config();
            $g   = Tiger_Model_Config::SCOPE_GLOBAL;

            $cfg->set($g, '', 'tiger.routing.override.docs.pattern', trim((string) $v['route_pattern'], '/'));
            $cfg->set($g, '', 'tiger.routing.override.docs.enabled', !empty($v['route_enabled']) ? '1' : '0');
            $cfg->set($g, '', 'tiger.routing.override.docs.priority', (string) max(0, (int) $v['route_priority']));

            $this->_success([], 'docs.settings.saved', '/docs/admin/settings');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Force a rebuild of THIS server's docs index cache. Handy on a single box or to warm after
     * editing content; in a multi-server fleet it rebuilds only the node that handled the request
     * (the others self-heal on their own next content change), so warm each server at deploy.
     */
    public function rebuild(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        try {
            $locale = defined('LANG') ? LANG : 'en';
            $idx    = (new Docs_Model_Docs())->rebuildIndex($locale);
            $count  = count($idx['search'] ?? []);
            $this->_success(['pages' => $count], 'docs.index.rebuilt');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }
}
