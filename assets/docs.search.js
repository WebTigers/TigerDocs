// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerDocs ⌘K search launcher.
 *
 * Opens on ⌘K / Ctrl+K anywhere, on "/" when you're not typing in a field, or by clicking any
 * [data-docs-search-open] button (the header launcher). Debounced queries POST to the Tiger /api
 * message endpoint (module=docs, service=search) and render ranked results with keyboard nav
 * (↑/↓ to move, Enter to open, Esc to close). Result URLs are base + '/' + slug, where base comes
 * from the modal's data-docs-base — matching the sidebar links exactly. Zero deps beyond the
 * theme's bundled Bootstrap modal.
 */
(function () {
    var modalEl = document.getElementById('docsSearchModal');
    if (!modalEl || modalEl._wired) { return; }
    modalEl._wired = 1;

    var base    = modalEl.getAttribute('data-docs-base') || '/docs';
    var input   = modalEl.querySelector('[data-docs-search-input]');
    var results = modalEl.querySelector('[data-docs-search-results]');
    var hint    = modalEl.querySelector('[data-docs-search-hint]');
    var empty   = modalEl.querySelector('[data-docs-search-empty]');
    var modal   = (window.bootstrap && bootstrap.Modal) ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    var timer   = null, seq = 0, items = [], active = -1;

    function open() { if (modal) { modal.show(); } }

    modalEl.addEventListener('shown.bs.modal', function () { input.focus(); input.select(); });

    // Global shortcuts: ⌘K / Ctrl+K anywhere; "/" only when not already typing somewhere.
    document.addEventListener('keydown', function (e) {
        var t      = e.target || {};
        var typing = /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName || '') || t.isContentEditable;
        if ((e.key === 'k' || e.key === 'K') && (e.metaKey || e.ctrlKey)) { e.preventDefault(); open(); return; }
        if (e.key === '/' && !typing && !e.metaKey && !e.ctrlKey) { e.preventDefault(); open(); }
    });

    document.querySelectorAll('[data-docs-search-open]').forEach(function (b) {
        b.addEventListener('click', open);
    });

    function esc(s) {
        return (s == null ? '' : String(s)).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // Escape text, THEN wrap each query word in <mark> (escaped text has no markup to break).
    function highlight(text, q) {
        var out = esc(text);
        (q || '').trim().split(/\s+/).forEach(function (w) {
            if (w.length < 2) { return; }
            var re = new RegExp('(' + w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig');
            out = out.replace(re, '<mark>$1</mark>');
        });
        return out;
    }

    function render(q) {
        results.innerHTML = '';
        active = -1;
        var short = q.trim().length < 2;
        if (!items.length) {
            hint.classList.toggle('d-none', !short);
            empty.classList.toggle('d-none', short);
            return;
        }
        hint.classList.add('d-none');
        empty.classList.add('d-none');
        items.forEach(function (it, i) {
            var a = document.createElement('a');
            a.href = it.url || (base + '/' + it.slug);   // server sends a collection-aware url
            a.className = 'docs-search-item d-block text-decoration-none rounded px-3 py-2';
            a.innerHTML =
                '<div class="d-flex align-items-center gap-2">'
              +     '<i class="fa-solid fa-file-lines text-body-secondary"></i>'
              +     '<span class="fw-semibold">' + highlight(it.title, q) + '</span>'
              +     (it.section ? '<span class="badge text-bg-light ms-auto">' + esc(it.section) + '</span>' : '')
              + '</div>'
              + (it.snippet ? '<div class="small text-body-secondary mt-1">' + highlight(it.snippet, q) + '</div>' : '');
            a.addEventListener('mousemove', function () { setActive(i); });
            results.appendChild(a);
        });
        setActive(0);
    }

    function setActive(i) {
        var els = results.querySelectorAll('.docs-search-item');
        if (!els.length) { return; }
        active = (i + els.length) % els.length;
        els.forEach(function (el, j) { el.classList.toggle('active', j === active); });
        els[active].scrollIntoView({ block: 'nearest' });
    }

    function run(q) {
        var mine = ++seq;
        if (q.trim().length < 2) { items = []; render(q); return; }
        var body = new URLSearchParams({ module: 'docs', service: 'search', method: 'query', q: q });
        fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (mine !== seq) { return; }                        // ignore a stale response
                items = (res && res.data && res.data.results) || [];
                render(q);
            })
            .catch(function () { if (mine === seq) { items = []; render(q); } });
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        var q = input.value;
        timer = setTimeout(function () { run(q); }, 160);
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown')    { e.preventDefault(); setActive(active + 1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(active - 1); }
        else if (e.key === 'Enter') {
            var el = results.querySelectorAll('.docs-search-item')[active];
            if (el) { e.preventDefault(); window.location = el.href; }
        }
    });
})();
