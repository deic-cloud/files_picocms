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

    /*
     * 3. Presentation mode: the page as slides. Slide 1 is the masthead (eyebrow,
     *    title, description); then one slide per `##` section, and a `---` inside a
     *    section starts another. Slides are copies - the page itself is untouched.
     *    Each slide is laid out at the page's own column width in the theme's own
     *    styles, then scaled as a whole to fill the screen (long slides shrink to
     *    fit). Start: the Present button, the P key, or a #slide-N address.
     *    Keys: → Space PageDown next, ← PageUp back, Home/End, F full screen,
     *    Esc leave. Click: left third back, elsewhere next (links keep working).
     */
    function initPresent() {
        var main = document.querySelector('main.content');
        var masthead = document.querySelector('.masthead');
        if (!main || !masthead) { return; }

        function buildSlides() {
            var slides = [];
            var head = masthead.cloneNode(true);
            Array.prototype.forEach.call(head.querySelectorAll('.edit-button, .present-button'), function (b) { b.remove(); });
            slides.push({ kind: 'masthead', nodes: [head] });
            var current = [];
            function flush() {
                var meaningful = current.some(function (n) { return n.nodeType === 1 || (n.textContent || '').trim() !== ''; });
                if (meaningful) { slides.push({ kind: 'content', nodes: current }); }
                current = [];
            }
            Array.prototype.forEach.call(main.childNodes, function (n) {
                if (n.nodeType === 1 && n.tagName === 'H2') { flush(); }
                if (n.nodeType === 1 && n.tagName === 'HR') { flush(); return; }
                current.push(n.cloneNode(true));
            });
            flush();
            return slides;
        }

        var deck = null, stage = null, counter = null, slides = [], index = 0;

        function fit() {
            if (!stage) { return; }
            stage.style.transform = 'none';
            var w = stage.offsetWidth, h = stage.scrollHeight;
            var scale = Math.min(window.innerWidth * 0.92 / w, window.innerHeight * 0.86 / h);
            stage.style.transform = 'translate(-50%, -50%) scale(' + scale + ')';
        }

        function show(i) {
            index = Math.max(0, Math.min(slides.length - 1, i));
            stage.innerHTML = '';
            stage.className = 'deck-stage ' + (slides[index].kind === 'masthead' ? 'deck-title' : 'content');
            slides[index].nodes.forEach(function (n) { stage.appendChild(n.cloneNode(true)); });
            counter.textContent = (index + 1) + ' / ' + slides.length;
            if (history.replaceState) { history.replaceState(null, '', '#slide-' + (index + 1)); }
            fit();
        }

        function onKey(e) {
            if (e.altKey || e.ctrlKey || e.metaKey) { return; }
            var k = e.key;
            if (k === 'ArrowRight' || k === 'ArrowDown' || k === 'PageDown' || k === ' ' || k === 'Enter') { show(index + 1); }
            else if (k === 'ArrowLeft' || k === 'ArrowUp' || k === 'PageUp' || k === 'Backspace') { show(index - 1); }
            else if (k === 'Home') { show(0); }
            else if (k === 'End') { show(slides.length - 1); }
            else if (k === 'f' || k === 'F') { toggleFullscreen(); }
            else if (k === 'Escape') { stop(); }
            else { return; }
            e.preventDefault();
        }

        function onClick(e) {
            if (e.target.closest('a, button, input, summary')) { return; }
            show(e.clientX < window.innerWidth / 3 ? index - 1 : index + 1);
        }

        function toggleFullscreen() {
            if (document.fullscreenElement) { document.exitFullscreen().catch(function () {}); }
            else if (document.documentElement.requestFullscreen) { document.documentElement.requestFullscreen().catch(function () {}); }
        }

        function start(at, fullscreen) {
            if (deck) { return; }
            slides = buildSlides();
            deck = document.createElement('div');
            deck.className = 'deck';
            deck.setAttribute('role', 'region');
            deck.setAttribute('aria-label', 'Presentation');
            stage = document.createElement('div');
            counter = document.createElement('div');
            counter.className = 'deck-counter';
            deck.appendChild(stage);
            deck.appendChild(counter);
            document.body.appendChild(deck);
            document.body.classList.add('presenting');
            document.addEventListener('keydown', onKey);
            deck.addEventListener('click', onClick);
            window.addEventListener('resize', fit);
            if (fullscreen) { toggleFullscreen(); }
            show(at || 0);
        }

        function stop() {
            if (!deck) { return; }
            document.removeEventListener('keydown', onKey);
            window.removeEventListener('resize', fit);
            deck.remove();
            deck = stage = counter = null;
            document.body.classList.remove('presenting');
            if (document.fullscreenElement) { document.exitFullscreen().catch(function () {}); }
            if (history.replaceState) { history.replaceState(null, '', location.pathname + location.search); }
        }

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'present-button';
        btn.textContent = 'Present';
        btn.title = 'Show this page as slides (P) - arrows to move, Esc to leave';
        btn.addEventListener('click', function () { start(0, true); });
        masthead.appendChild(btn);

        document.addEventListener('keydown', function (e) {
            if (deck || e.altKey || e.ctrlKey || e.metaKey) { return; }
            if ((e.key === 'p' || e.key === 'P') && !e.target.closest('input, textarea, [contenteditable]')) {
                e.preventDefault();
                start(0, true);
            }
        });
        var m = /^#slide-(\d+)$/.exec(location.hash);
        if (m) { start(parseInt(m[1], 10) - 1, false); }
    }

    document.addEventListener('DOMContentLoaded', function () {
        initShowFiles();
        initToc();
        initPresent();
    });
})();
