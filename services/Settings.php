<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Docs_Service_Settings — /api service for the Docs settings screen.
 *
 * Validates Docs_Form_Settings, then writes the public route override to the DB `config`
 * table (scope=global) via Tiger_Model_Config as tiger.routing.override.docs.* — the
 * live-override tier, effective next request, no deploy. Tiger_Routing_Overrides reads exactly
 * these keys. The content source URL (`docs.content_url`) is written the same way, for the same
 * reason: pointing a fleet at a different content path should not need a deploy. ACL: admin+
 * (configs/acl.ini).
 *
 * @api
 */
class Docs_Service_Settings extends Tiger_Service_Service
{
    /**
     * Validate the settings form and persist the public route override to config.
     *
     * @param  array $params the submitted Docs_Form_Settings values
     * @return void          responds via _success/_formErrors/_error
     */
    public function save(array $params): void
    {
        if (!$this->_isAllowed('save')) { $this->_error('core.api.error.not_allowed'); return; }

        $form = new Docs_Form_Settings();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $v = $form->getValues();

        try {
            $cfg = new Tiger_Model_Config();
            $g   = Tiger_Model_Config::SCOPE_GLOBAL;

            $cfg->set($g, '', 'tiger.routing.override.docs.pattern', trim((string) $v['route_pattern'], '/'));
            $cfg->set($g, '', 'tiger.routing.override.docs.enabled', !empty($v['route_enabled']) ? '1' : '0');
            $cfg->set($g, '', 'tiger.routing.override.docs.priority', (string) max(0, (int) $v['route_priority']));

            // The content source. Blank means "no remote source" — forget() rather than storing an
            // empty string, so the key reads as unset and the lower content roots take over again.
            $url = rtrim(trim((string) ($v['content_url'] ?? '')), '/');
            if ($url === '') {
                $cfg->forget($g, '', 'docs.content_url');
            } else {
                $cfg->set($g, '', 'docs.content_url', $url);
            }

            $this->_success([], 'docs.settings.saved', '/docs/admin/settings');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Force a rebuild of THIS server's docs index cache. Handy on a single box or to warm after
     * editing content; in a multi-server fleet it rebuilds only the node that handled the request
     * (the others self-heal on their own next content change), so warm each server at deploy.
     *
     * @param  array $params the request payload (unused)
     * @return void          responds via _success/_error with the indexed page count
     */
    public function rebuild(array $params): void
    {
        if (!$this->_isAllowed('rebuild')) { $this->_error('core.api.error.not_allowed'); return; }

        try {
            $locale = defined('LANG') ? LANG : 'en';
            $idx    = (new Docs_Model_Docs())->rebuildIndex($locale);
            $count  = count($idx['search'] ?? []);
            $this->_success(['pages' => $count], 'docs.index.rebuilt');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Fetch the docs content from the remote source into THIS server's mirror, then refresh the
     * index so the new pages show at once.
     *
     * Per-server, like the index rebuild — the scheduled job keeps every other node current on its
     * own tick, so this button is for "I just published, show me now" rather than a fleet action.
     *
     * @param  array $params the request payload (unused)
     * @return void          responds via _success/_error with the file + change counts
     */
    public function fetchContent(array $params): void
    {
        if (!$this->_isAllowed('fetchContent')) { $this->_error('core.api.error.not_allowed'); return; }

        try {
            $remote = new Docs_Model_Remote();
            if (!$remote->isEnabled()) { $this->_error('docs.content.no_source'); return; }

            $res = $remote->sync();
            if ($res['error'] !== '') {
                $this->_error(APPLICATION_ENV !== 'production' ? $res['error'] : 'docs.content.fetch_failed');
                return;
            }

            (new Docs_Model_Docs())->rebuildIndex(defined('LANG') ? LANG : 'en');

            $this->_success([
                'files'     => (int) $res['files'],
                'changed'   => (int) $res['changed'],
                'removed'   => (int) $res['removed'],
                'generated' => (string) $res['generated'],
            ], 'docs.content.fetched');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Rebuild this server's generated API reference (docblocks → tiger:doc pages) into
     * var/docs-generated — the platform `reference` collection + a Reference section per module —
     * then refresh the index so it shows. Same work as the bin/build-reference.php deploy hook, as
     * an admin button. Per-server, like the index rebuild; generated docs are never committed.
     *
     * @param  array $params the request payload (unused)
     * @return void          responds via _success/_error with the page + per-target counts
     */
    public function buildReference(array $params): void
    {
        if (!$this->_isAllowed('buildReference')) { $this->_error('core.api.error.not_allowed'); return; }

        try {
            require_once dirname(__DIR__) . '/bin/reference.php';   // defines Docs_Reference_Generator
            $locale  = defined('LANG') ? LANG : 'en';
            $appRoot = defined('APPLICATION_PATH') ? dirname(APPLICATION_PATH) : dirname(__DIR__, 4);

            $res = (new Docs_Reference_Generator())->buildAll($appRoot, $locale);
            (new Docs_Model_Docs())->rebuildIndex($locale);         // pick the new pages up now

            $this->_success(['pages' => (int) $res['total'], 'targets' => $res['targets']], 'docs.reference.built');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }
}
