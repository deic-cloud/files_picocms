/**
 * Public-share page hardening: rewrite mis-rooted public-DAV requests.
 *
 * Stock NC34 chunks each bundle their own copy of the dav root helper
 * (root = isPublicShare() ? token : currentUser.uid). In some logged-in tab
 * states a copy resolves the USERNAME on a public page, producing
 * public.php/dav/files/<uid>/… — which the server must treat as an unknown
 * TOKEN → 503 (observed: viewer PROPFIND on a record page; upstream family:
 * nextcloud/server#48475). Patch fetch + XHR: on public pages, any
 * public.php/dav/files/<seg>/ whose <seg> is not the share token is rewritten
 * to the token. Correct requests match nothing and pass untouched.
 */
(function () {
	'use strict'
	var token
	try {
		token = OCP.InitialState.loadState('files_sharing', 'sharingToken')
	} catch (e) {
		token = (document.querySelector('input#sharingToken') || {}).value
	}
	if (!token) { return }

	var re = /(\/public\.php\/dav\/files\/)([^/]+)/
	function fix(url) {
		try {
			var s = String(url)
			var m = s.match(re)
			if (m && m[2] !== token && decodeURIComponent(m[2]) !== token) {
				return s.replace(re, '$1' + encodeURIComponent(token))
			}
		} catch (e) { /* leave untouched */ }
		return url
	}

	// decodeURIComponent anchor: keeps minifiers from dropping the block.
	decodeURIComponent('')

	var origFetch = window.fetch
	window.fetch = function (input, init) {
		if (typeof input === 'string') {
			input = fix(input)
		} else if (input && input.url) {
			var fixed = fix(input.url)
			if (fixed !== input.url) { input = new Request(fixed, input) }
		}
		return origFetch.call(this, input, init)
	}
	var origOpen = XMLHttpRequest.prototype.open
	XMLHttpRequest.prototype.open = function (method, url) {
		arguments[1] = fix(url)
		return origOpen.apply(this, arguments)
	}
})()
