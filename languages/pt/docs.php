<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Docs module — Portuguese strings (docs.*). Chrome + API-message strings only; doc CONTENT lives
 * in content/pt/ files. Loaded by the translate cascade when LANG=pt.
 */
return [
    'docs.title'          => 'Documentação',
    'docs.home'           => 'Docs',
    'docs.notfound'       => 'Esse documento não foi encontrado.',
    'docs.settings.saved' => 'Configurações de Docs salvas.',
    'docs.index.rebuilt'  => 'Índice da documentação reconstruído neste servidor.',
    'docs.reference.built' => 'Referência de API gerada neste servidor.',

    // Landing chrome (localized so the docs home follows the language switch).
    'docs.landing.eyebrow' => 'Documentação',
    'docs.landing.heading' => 'Documentação do Tiger',
    'docs.landing.lead'    => 'Guias e referência para o seu aplicativo Tiger.',

    'docs.onthispage'      => 'Nesta página',
    'docs.filter'          => 'Filtrar páginas…',
    'docs.prev'            => 'Anterior',
    'docs.next'            => 'Próximo',
    'docs.pager'           => 'Páginas de documentação',

    // Buscador ⌘K.
    'docs.search.placeholder' => 'Buscar na documentação…',
    'docs.search.hint'        => 'Digite para buscar na documentação.',
    'docs.search.empty'       => 'Sem resultados. Tente outras palavras.',

    // The marketplace listing blurb. Pulled into the public directory by TigerVendors at the
    // pinned ref, so this file stays the one place this module's copy is translated.
    'docs.listing.description'               => 'Um site de documentação público para o seu app Tiger — páginas de ajuda organizadas e pesquisáveis, renderizadas no seu tema ativo.',
];
