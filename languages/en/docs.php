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

    // Landing chrome (localized so the docs home follows the language switch).
    'docs.landing.eyebrow' => 'Documentation',
    'docs.landing.heading' => 'Tiger Docs',
    'docs.landing.lead'    => 'Guides and reference for your Tiger app.',
];
