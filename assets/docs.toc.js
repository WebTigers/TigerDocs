// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerDocs "On this page" scrollspy + sliding marker.
 *
 * Highlights the heading you're currently reading and slides a primary-colour marker along the
 * TOC's left rail to match. Pure vanilla, zero deps. The active heading is the last one whose top
 * has scrolled above the header offset; clicking a link activates it immediately. Geometry is read
 * from each link's offsetTop/Height within the (position:relative) nav, so it's independent of the
 * sticky scroll. Recomputes on resize; respects prefers-reduced-motion via CSS.
 */
(function () {
    var toc = document.getElementById('docsToc');
    if (!toc || toc._wired) { return; }
    toc._wired = 1;

    var marker = toc.querySelector('.docs-toc-marker');
    var links  = Array.prototype.slice.call(toc.querySelectorAll('[data-toc-link]'));
    if (!marker || !links.length) { return; }

    var byId     = {};
    var headings = [];
    links.forEach(function (a) {
        var id = (a.getAttribute('href') || '').replace(/^#/, '');
        var h  = id && document.getElementById(id);
        if (h) { byId[id] = a; headings.push(h); }
    });
    if (!headings.length) { return; }

    var OFFSET  = 90;   // header clearance (~4.5rem sticky + a little), matches scroll-margin-top
    var current = null;

    function place(link) {
        marker.style.height    = link.offsetHeight + 'px';
        marker.style.transform = 'translateY(' + link.offsetTop + 'px)';
        marker.style.opacity   = '1';
    }

    function activate(link) {
        if (!link) { return; }
        if (link !== current) {
            current = link;
            links.forEach(function (a) { a.classList.toggle('active', a === link); });
        }
        place(link);   // always re-place (handles resize / font reflow)
    }

    var ticking = false;
    function spy() {
        ticking = false;
        var active = headings[0];
        for (var i = 0; i < headings.length; i++) {
            if (headings[i].getBoundingClientRect().top <= OFFSET) { active = headings[i]; } else { break; }
        }
        // At the very bottom, force the last heading (the last section is often too short to hit the top).
        if ((window.innerHeight + window.scrollY) >= (document.documentElement.scrollHeight - 4)) {
            active = headings[headings.length - 1];
        }
        activate(byId[active.id]);
    }
    function onScroll() {
        if (!ticking) { ticking = true; requestAnimationFrame(spy); }
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', function () { if (current) { place(current); } onScroll(); });
    links.forEach(function (a) { a.addEventListener('click', function () { activate(a); }); });
    spy();   // initial
})();
