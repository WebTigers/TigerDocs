<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Docs_Service_Search — /api search for the docs (powers the ⌘K launcher on both surfaces).
 *
 * `scope=public` (default) searches PUBLIC docs and is guest-allowed (the docs are public).
 * `scope=admin` searches ADMIN docs (the in-app help center) and is admin-only. Each hit gets a
 * ready-to-use, surface-correct `url`.
 *
 * Called as { module:'docs', service:'search', method:'query', q:'…', scope:'public'|'admin' }.
 *
 * @api
 */
class Docs_Service_Search extends Tiger_Service_Service
{
    /**
     * Search the docs and return ranked, surface-correct hits over /api.
     *
     * @param  array $params the request payload (`q`, optional `scope` = public|admin)
     * @return void          responds via _success/_error (results carry a ready-to-use `url`)
     */
    public function query(array $params): void
    {
        $q      = isset($params['q']) ? (string) $params['q'] : '';
        $locale = defined('LANG') ? LANG : 'en';
        $scope  = (($params['scope'] ?? '') === Docs_Model_Docs::VIS_ADMIN)
            ? Docs_Model_Docs::VIS_ADMIN : Docs_Model_Docs::VIS_PUBLIC;

        if ($scope === Docs_Model_Docs::VIS_ADMIN && !$this->_isAdmin()) {
            $this->_error('core.api.error.not_allowed');
            return;
        }

        try {
            $default = Docs_Model_Docs::DEFAULT_COLLECTION;
            $base    = $this->_docsBase();
            $results = (new Docs_Model_Docs())->search($q, $locale, $scope);

            foreach ($results as &$r) {
                $col = (string) ($r['collection'] ?? '');
                if ($scope === Docs_Model_Docs::VIS_ADMIN) {
                    $r['url'] = '/docs/admin/help/' . $col . '/' . $r['slug'];      // admin help, always namespaced
                } else {
                    $prefix   = $base . ($col === $default ? '' : '/' . $col);      // guide prefix-less
                    $r['url'] = $prefix . '/' . $r['slug'];
                }
            }
            unset($r);

            $this->_success(['results' => $results]);
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /** The docs' active public base (route-override prefix), defaulting to /docs. */
    protected function _docsBase()
    {
        $o = Tiger_Routing_Overrides::get('docs');
        $prefix = ($o && ($o['prefix'] ?? '') !== '') ? $o['prefix'] : 'docs';
        return '/' . $prefix;
    }
}
