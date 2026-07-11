/*! SPDX-License-Identifier: BSD-3-Clause · © 2026 WebTigers · Tiger™/WebTigers™ are trademarks */
/*
 * TigerDocs width toggle (Phase 1). Normal | Full-width, remembered per-browser in localStorage.
 * The attribute is applied pre-paint by a tiny inline script in the docs header (no flash); this
 * file wires the header toggle button and keeps its state in sync.
 */
(function () {
    'use strict';

    var KEY = 'tigerdocs.layout';

    function read()  { try { return JSON.parse(localStorage.getItem(KEY)) || {}; } catch (e) { return {}; } }
    function write(o) { try { localStorage.setItem(KEY, JSON.stringify(o)); } catch (e) {} }

    /** Apply a mode to <html> + reflect it on every toggle button (icon, title, pressed state). */
    function sync(mode) {
        var full = mode === 'full';
        document.documentElement.setAttribute('data-docs-width', full ? 'full' : 'normal');
        var btns = document.querySelectorAll('[data-docs-width-toggle]');
        for (var i = 0; i < btns.length; i++) {
            var b = btns[i];
            b.setAttribute('aria-pressed', full ? 'true' : 'false');
            b.title = full ? 'Normal width' : 'Full width';
            var icon = b.querySelector('i');
            if (icon) { icon.className = 'fa-solid ' + (full ? 'fa-compress' : 'fa-expand'); }
        }
    }

    // Delegated click — the button lives in the theme header, rendered before this script.
    document.addEventListener('click', function (e) {
        var t = e.target.closest ? e.target.closest('[data-docs-width-toggle]') : null;
        if (!t) { return; }
        e.preventDefault();
        var o = read();
        o.mode = (o.mode === 'full') ? 'normal' : 'full';
        write(o);
        sync(o.mode);
    });

    // On load, sync button chrome to the (already pre-paint applied) stored mode.
    if (document.readyState !== 'loading') { sync(read().mode); }
    else { document.addEventListener('DOMContentLoaded', function () { sync(read().mode); }); }
})();
