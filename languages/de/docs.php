<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Docs module — German strings (docs.*). Chrome + API-message strings only; doc CONTENT lives
 * in content/de/ files. Loaded by the translate cascade when LANG=de.
 */
return [
    'docs.title'          => 'Dokumentation',
    'docs.home'           => 'Docs',
    'docs.notfound'       => 'Dieses Dokument wurde nicht gefunden.',
    'docs.settings.saved' => 'Docs-Einstellungen gespeichert.',
    'docs.index.rebuilt'  => 'Dokumentationsindex auf diesem Server neu erstellt.',
    'docs.reference.built' => 'API-Referenz auf diesem Server generiert.',

    // Landing chrome (localized so the docs home follows the language switch).
    'docs.landing.eyebrow' => 'Dokumentation',
    'docs.landing.heading' => 'Tiger-Dokumentation',
    'docs.landing.lead'    => 'Anleitungen und Referenz für Ihre Tiger-App.',

    'docs.onthispage'      => 'Auf dieser Seite',
    'docs.filter'          => 'Seiten filtern…',
    'docs.prev'            => 'Zurück',
    'docs.next'            => 'Weiter',
    'docs.pager'           => 'Dokumentationsseiten',

    // ⌘K-Suche.
    'docs.search.placeholder' => 'Docs durchsuchen…',
    'docs.search.hint'        => 'Tippen, um die Dokumentation zu durchsuchen.',
    'docs.search.empty'       => 'Keine Ergebnisse. Versuchen Sie andere Begriffe.',

    // The marketplace listing blurb. Pulled into the public directory by TigerVendors at the
    // pinned ref, so this file stays the one place this module's copy is translated.
    'docs.listing.description'               => 'Eine öffentliche Dokumentations-Website für Ihre Tiger-App — strukturierte, durchsuchbare Hilfeseiten, dargestellt in Ihrem aktiven Theme.',
];
