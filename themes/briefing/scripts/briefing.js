/*
 * briefing theme — small vanilla-JS enhancements (no jQuery):
 *  1. "Show files" button → open the Files app at the page's folder.
 *  2. Table of contents (when front-matter `Toc: true`): a box fixed in the
 *     upper right, folded by default ([show]/[hide]), smooth in-page
 *     scrolling, active-section highlighting (scrollspy), and - as in the blog
 *     theme - a "Presentation mode" checkbox: when ticked, clicking a line
 *     shows only that section (its heading and everything up to the next
 *     heading of the same or a higher level). Pico supplies #toc and the
 *     heading anchors.
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
        toggle.textContent = '[show]';
        toggle.setAttribute('role', 'button');
        toggle.setAttribute('tabindex', '0');
        var present = document.createElement('input');
        present.type = 'checkbox';
        present.id = 'presentation_mode';
        present.title = 'Presentation mode: show only the section you click';
        present.setAttribute('aria-label', 'Presentation mode');
        header.appendChild(label);
        header.appendChild(toggle);
        header.appendChild(present);
        toc.insertBefore(header, toc.firstChild);

        function setHidden(hidden) {
            list.style.display = hidden ? 'none' : '';
            toggle.textContent = hidden ? '[show]' : '[hide]';
        }
        toggle.addEventListener('click', function () {
            setHidden(list.style.display !== 'none');
        });
        toggle.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle.click(); }
        });

        // Folded by default, as in the blog theme.
        setHidden(true);

        // Pair each link with its target heading.
        var targets = [];
        Array.prototype.forEach.call(list.querySelectorAll('a'), function (a) {
            var href = a.getAttribute('href') || '';
            var i = href.indexOf('#');
            var el = i >= 0 ? document.getElementById(decodeURIComponent(href.slice(i + 1))) : null;
            if (el) { targets.push({ link: a, el: el }); }
        });
        if (!targets.length) { return; }

        // Presentation mode: everything in the content area is hidden except the
        // chosen section = its heading and what follows, up to the next heading of
        // the same or a higher level (a ## brings its ### along).
        var main = document.querySelector('main.content');
        var current = null;
        function level(el) { return /^H[1-6]$/.test(el.tagName) ? parseInt(el.tagName.charAt(1), 10) : 0; }
        function showSection(heading) {
            current = heading;
            var children = Array.prototype.slice.call(main.children);
            var on = false, lvl = level(heading);
            children.forEach(function (el) {
                if (el === heading) { on = true; }
                else if (on && level(el) && level(el) <= lvl) { on = false; }
                el.classList.toggle('pm-hidden', !on);
                el.classList.toggle('pm-first', el === heading);
            });
            targets.forEach(function (t) { t.link.classList.toggle('active', t.el === heading); });
            window.scrollTo(0, 0);
        }
        function setPresentation(on) {
            document.body.classList.toggle('presentation-mode', on);
            if (on) {
                var active = targets.filter(function (t) { return t.link.classList.contains('active'); })[0];
                showSection((active || targets[0]).el);
            } else {
                Array.prototype.forEach.call(main.children, function (el) { el.classList.remove('pm-hidden', 'pm-first'); });
                if (current) { current.scrollIntoView({ block: 'start' }); }
                current = null;
                updateActive();
            }
        }
        if (main) {
            present.addEventListener('change', function () { setPresentation(present.checked); });
        } else {
            present.hidden = true;
        }

        // Smooth in-page scroll (respecting reduced-motion); in presentation mode,
        // show the section instead.
        var smooth = !(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
        targets.forEach(function (t) {
            t.link.addEventListener('click', function (ev) {
                ev.preventDefault();
                if (present.checked && main) {
                    showSection(t.el);
                } else {
                    t.el.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto', block: 'start' });
                }
                if (history.replaceState) { history.replaceState(null, '', t.link.getAttribute('href')); }
            });
        });

        // Scrollspy: mark the section nearest the top as active.
        function updateActive() {
            if (current) { return; }   // presentation mode marks the shown section
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
