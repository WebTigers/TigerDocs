// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerDocs syntax highlighting — highlight.js (vendored, no CDN).
 *
 * The markdown renderer emits <pre><code class="language-XXX">, so highlight.js resolves each
 * block's language from that class. Loaded after highlight.min.js + php-template.min.js (both
 * deferred, in order). `phtml` fences are aliased to `php-template` (PHP embedded in HTML).
 * Colours come from docs.hljs.css, scoped to data-bs-theme so they follow light/dark on their own.
 */
(function () {
    if (!window.hljs) { return; }

    // ```phtml  ->  the php-template grammar (HTML with embedded <?php ?> / <?= ?>).
    try { hljs.registerAliases(['phtml'], { languageName: 'php-template' }); } catch (e) { /* alias optional */ }

    document.querySelectorAll('.tiger-docs-body pre code').forEach(function (el) {
        try { hljs.highlightElement(el); } catch (e) { /* leave the block as plain text on any parse error */ }
    });
})();
