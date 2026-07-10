<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Docs_Service_Search — /api search for the public docs (powers the ⌘K launcher).
 *
 * Public (guest-allowed, read-only — like Tiger_Service_Location): the docs are public, so
 * search over them is too. Searches every collection's file docs in the caller's request locale
 * (Docs_Model_Docs::search); returns ranked hits with a ready-to-use `url` (collection-aware:
 * Guide is prefix-less, other collections are namespaced under the docs base).
 *
 * Called as { module:'docs', service:'search', method:'query', q:'…' }.
 */
class Docs_Service_Search extends Tiger_Service_Service
{
    public function query(array $params): void
    {
        $q      = isset($params['q']) ? (string) $params['q'] : '';
        $locale = defined('LANG') ? LANG : 'en';

        try {
            $base    = $this->_docsBase();
            $default = Docs_Model_Docs::DEFAULT_COLLECTION;
            $results = (new Docs_Model_Docs())->search($q, $locale);

            foreach ($results as &$r) {
                $col   = (string) ($r['collection'] ?? $default);
                $prefix = $base . ($col === $default ? '' : '/' . $col);
                $r['url'] = $prefix . '/' . $r['slug'];
            }
            unset($r);

            $this->_success(['results' => $results]);
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /** The docs' active public base (the effective route-override prefix), defaulting to /docs. */
    protected function _docsBase()
    {
        $o = Tiger_Routing_Overrides::get('docs');
        $prefix = ($o && ($o['prefix'] ?? '') !== '') ? $o['prefix'] : 'docs';
        return '/' . $prefix;
    }
}
