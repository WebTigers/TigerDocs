<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Docs module — English strings (docs.*). Loaded on top of core/app strings by the translate
 * cascade; resolved in the caller's locale. Doc CONTENT is not translated here — it lives in
 * content/<locale>/ files (or DB doc rows); these are the chrome + API-message strings only.
 */
return [
    'docs.title'          => 'Documentation',
    'docs.home'           => 'Docs',
    'docs.notfound'       => 'That document could not be found.',
    'docs.settings.saved' => 'Docs settings saved.',
    'docs.index.rebuilt'  => 'Docs index rebuilt on this server.',
    'docs.reference.built' => 'API reference generated on this server.',

    // Landing chrome (localized so the docs home follows the language switch).
    'docs.landing.eyebrow' => 'Documentation',
    'docs.landing.heading' => 'Tiger Docs',
    'docs.landing.lead'    => 'Guides and reference for your Tiger app.',

    'docs.onthispage'      => 'On this page',
    'docs.filter'          => 'Filter pages…',
    'docs.prev'            => 'Previous',
    'docs.next'            => 'Next',
    'docs.pager'           => 'Documentation pages',

    // ⌘K search launcher.
    'docs.search.placeholder' => 'Search docs…',
    'docs.search.hint'        => 'Type to search the documentation.',
    'docs.search.empty'       => 'No results. Try different words.',
];
