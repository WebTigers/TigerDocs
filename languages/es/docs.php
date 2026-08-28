<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Docs module — Spanish strings (docs.*). Chrome + API-message strings only; doc CONTENT lives
 * in content/es/ files. Loaded by the translate cascade when LANG=es.
 */
return [
    'docs.title'          => 'Documentación',
    'docs.home'           => 'Docs',
    'docs.notfound'       => 'No se encontró ese documento.',
    'docs.settings.saved' => 'Configuración de Docs guardada.',
    'docs.index.rebuilt'  => 'Índice de documentación reconstruido en este servidor.',
    'docs.reference.built' => 'Referencia de API generada en este servidor.',

    // Landing chrome (localized so the docs home follows the language switch).
    'docs.landing.eyebrow' => 'Documentación',
    'docs.landing.heading' => 'Documentación de Tiger',
    'docs.landing.lead'    => 'Guías y referencia para tu aplicación Tiger.',

    'docs.onthispage'      => 'En esta página',
    'docs.filter'          => 'Filtrar páginas…',
    'docs.prev'            => 'Anterior',
    'docs.next'            => 'Siguiente',
    'docs.pager'           => 'Páginas de documentación',

    // Buscador ⌘K.
    'docs.search.placeholder' => 'Buscar en la documentación…',
    'docs.search.hint'        => 'Escribe para buscar en la documentación.',
    'docs.search.empty'       => 'Sin resultados. Prueba con otras palabras.',

    // The marketplace listing blurb. Pulled into the public directory by TigerVendors at the
    // pinned ref, so this file stays the one place this module's copy is translated.
    'docs.listing.description'               => 'Un sitio de documentación público para tu aplicación Tiger: páginas de ayuda organizadas y con búsqueda, renderizadas en tu tema activo.',
];
