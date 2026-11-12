/**
 * Back button on public share pages (/s/<token>).
 *
 * Loaded by Application::boot for share-page addresses, so that it runs BEFORE
 * the Files app: the automatic preview of a single-file share pushes its history
 * entry while the page starts, before scripts added later get a say.
 *
 * Nextcloud's share page records every viewer open/close and every address tweak
 * (?openfile, &editing=false, ?dir=/) as a new history entry, so leaving a single
 * notebook took five clicks on Back. Rules, all within one folder (the dir
 * parameter); changing folder keeps its own entry, so Back goes up:
 *  - a file the VISITOR opens gets one entry, and Back closes it (the Viewer
 *    replaceState's itself in: promoted to a push only after a click or key;
 *    the automatic preview on page load never adds one);
 *  - closing that file with its close button steps back over that entry instead
 *    of pushing yet another one;
 *  - any other push within the same folder becomes a replace.
 */
;(function () {
	'use strict'
	function hasOpenfile(u) {
		try { return /[?&](openfile|opendetails)\b/.test(String(u)) } catch (e) { return false }
	}
	function dirOf(u) {
		try { return new URL(String(u), location.href).searchParams.get('dir') || '/' } catch (e) { return '/' }
	}
	var userActed = false
	;['click', 'keydown', 'touchstart'].forEach(function (ev) {
		window.addEventListener(ev, function () { userActed = true }, true)
	})
	var pushedOpen = false
	var origPush = history.pushState
	var origReplace = history.replaceState
	history.replaceState = function (state, title, url) {
		if (url && userActed && hasOpenfile(url) && !hasOpenfile(location.href)) {
			pushedOpen = true
			return origPush.call(history, state, title, url)
		}
		return origReplace.apply(history, arguments)
	}
	history.pushState = function (state, title, url) {
		if (url && dirOf(url) === dirOf(location.href)) {
			if (pushedOpen && hasOpenfile(location.href) && !hasOpenfile(url)) {
				pushedOpen = false
				history.back()   // closing the file the visitor opened: back to before it
				return
			}
			if (userActed && hasOpenfile(url) && !hasOpenfile(location.href)) {
				pushedOpen = true
				return origPush.apply(history, arguments)
			}
			return origReplace.call(history, state, title, url)
		}
		return origPush.apply(history, arguments)
	}
	window.addEventListener('popstate', function () {
		if (!hasOpenfile(location.href)) {
			pushedOpen = false
			try {
				if (window.OCA && OCA.Viewer && typeof OCA.Viewer.close === 'function') {
					OCA.Viewer.close()
				}
			} catch (e) { /* nothing to close */ }
		}
	})
})()
