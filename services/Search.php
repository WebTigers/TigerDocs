<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Docs_Service_Search — /api search for the public docs (powers the ⌘K launcher).
 *
 * Public (guest-allowed, read-only — like Tiger_Service_Location): the docs are public, so
 * search over them is too. Searches the shipped doc files in the caller's request locale
 * (Docs_Model_Docs::search); returns ranked {slug,title,section,snippet} hits. The client
 * builds each result URL from the docs base it already knows, so no route logic lives here.
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
            $results = (new Docs_Model_Docs())->search($q, $locale);
            $this->_success(['results' => $results]);
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }
}
