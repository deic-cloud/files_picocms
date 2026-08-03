/*
 * briefing theme — "Show files" button.
 * Vanilla JS, no jQuery: navigate to the Nextcloud Files app at the folder
 * that holds the current page, so the author can edit/upload the source .md.
 * (The briefing theme is presentation-first; in-page editing is intentionally
 * out of scope — author the Markdown in Files, or switch to the team/blog theme.)
 */
(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
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
    });
})();
