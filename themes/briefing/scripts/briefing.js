/*
 * briefing theme — small vanilla-JS enhancements (no jQuery):
 *  1. "Show files" button → open the Files app at the page's folder.
 *  2. Table of contents (when front-matter `Toc: true`): a collapsible
 *     "Contents" header, smooth in-page scrolling, and active-section
 *     highlighting (scrollspy). Pico supplies #toc and the heading anchors.
 */
(function () {
    'use strict';

    function initShowFiles() {
        var btn = document.querySelector('.edit-button');
        if (!btn) { return; }
        btn.addEventListener('click', function () {
            var host = btn.getAttribute('host') || '';
            var siteFolder = btn.getAttribute('folder') || '';
            var path = btn.getAttribute('path') || '';
            var parts = path.split('/');
            parts.pop(); // drop the file, keep its folder
            var dir = siteFolder + (parts.length ? '/' + parts.join('/') : '');
            window.location.href = host + '/index.php/apps/files/?dir=' + encodeURIComponent(dir);
        });
    }

    function initToc() {
        var toc = document.getElementById('toc');
        if (!toc) { return; }
        var list = toc.querySelector('ul');
        if (!list) { return; }

        // Collapsible "Contents" header.
        var header = document.createElement('div');
        header.id = 'toc_header';
        var label = document.createElement('span');
        label.textContent = 'Contents';
        var toggle = document.createElement('span');
        toggle.id = 'toc_toggle';
        toggle.textContent = '[hide]';
        toggle.setAttribute('role', 'button');
        toggle.setAttribute('tabindex', '0');
        header.appendChild(label);
        header.appendChild(toggle);
        toc.insertBefore(header, toc.firstChild);

        var userToggled = false;
        function setHidden(hidden) {
            list.style.display = hidden ? 'none' : '';
            toggle.textContent = hidden ? '[show]' : '[hide]';
        }
        toggle.addEventListener('click', function () {
            userToggled = true;
            setHidden(list.style.display !== 'none');
        });
        toggle.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle.click(); }
        });

        // Default state by width: expanded when floated in the right gutter (wide),
        // collapsed when inline under the masthead (narrow). Re-sync on breakpoint
        // change unless the reader has toggled it themselves.
        var wide = window.matchMedia ? window.matchMedia('(min-width: 1300px)') : null;
        setHidden(wide ? !wide.matches : false);
        if (wide) {
            var onChange = function (e) { if (!userToggled) { setHidden(!e.matches); } };
            if (wide.addEventListener) { wide.addEventListener('change', onChange); }
            else if (wide.addListener) { wide.addListener(onChange); }
        }

        // Pair each link with its target heading.
        var targets = [];
        Array.prototype.forEach.call(list.querySelectorAll('a'), function (a) {
            var href = a.getAttribute('href') || '';
            var i = href.indexOf('#');
            var el = i >= 0 ? document.getElementById(decodeURIComponent(href.slice(i + 1))) : null;
            if (el) { targets.push({ link: a, el: el }); }
        });
        if (!targets.length) { return; }

        // Smooth in-page scroll (respecting reduced-motion).
        var smooth = !(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
        targets.forEach(function (t) {
            t.link.addEventListener('click', function (ev) {
                ev.preventDefault();
                t.el.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto', block: 'start' });
                if (history.replaceState) { history.replaceState(null, '', t.link.getAttribute('href')); }
            });
        });

        // Scrollspy: mark the section nearest the top as active.
        function updateActive() {
            var pos = window.scrollY || window.pageYOffset || 0;
            var best = null, bestTop = -Infinity;
            targets.forEach(function (t) {
                var top = t.el.getBoundingClientRect().top + pos;
                if (top - 90 <= pos && top > bestTop) { bestTop = top; best = t; }
            });
            targets.forEach(function (t) { t.link.classList.toggle('active', t === best); });
        }
        var ticking = false;
        window.addEventListener('scroll', function () {
            if (ticking) { return; }
            ticking = true;
            window.requestAnimationFrame(function () { updateActive(); ticking = false; });
        }, { passive: true });
        updateActive();
    }

    document.addEventListener('DOMContentLoaded', function () {
        initShowFiles();
        initToc();
    });
})();
