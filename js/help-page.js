/**
 * Deployment-branded /settings/help page (config-gated; see
 * HelpPageScriptListener). Reworks the stock "overview" help page:
 *   heading → "Documentation"; "<brand> documentation" button first;
 *   "Account documentation" → "Nextcloud documentation";
 *   admin-docs / general-docs / forum buttons removed.
 */
(function () {
	'use strict'
	document.addEventListener('DOMContentLoaded', function () {
		var state
		try {
			state = JSON.parse(atob(document.getElementById('initial-state-files_picocms-helpDocs').value))
		} catch (e) { return }
		if (!state || !state.url) { return }

		var body = document.querySelector('.help-content__body')
		var heading = document.querySelector('.help-content__heading')
		if (!body || !heading) { return } // embedded-knowledgebase mode or changed markup — leave stock page alone

		heading.textContent = t ? t('files_picocms', 'Documentation') : 'Documentation'

		// Remove: general docs, forum, admin docs (identified by their hrefs).
		body.querySelectorAll('a.button').forEach(function (a) {
			var href = a.getAttribute('href') || ''
			if (href === 'https://docs.nextcloud.com'
				|| href === 'https://help.nextcloud.com'
				|| href.indexOf('go.php?to=admin') !== -1) {
				a.remove()
			}
		})

		// Relabel the remaining Nextcloud user documentation button.
		var userDocs = body.querySelector('a.button[href*="docs.nextcloud.com"], a.button[href*="mode=user"]')
		if (userDocs) {
			userDocs.textContent = (t ? t('files_picocms', 'Nextcloud documentation') : 'Nextcloud documentation') + ' ↗'
		}

		// Our own documentation, first.
		var own = document.createElement('a')
		own.className = 'button'
		own.target = '_blank'
		own.rel = 'noreferrer noopener'
		own.href = state.url
		own.textContent = state.brand + ' ' + (t ? t('files_picocms', 'documentation') : 'documentation') + ' ↗'
		body.insertBefore(own, body.firstChild)
	})
})()
