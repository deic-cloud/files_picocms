/**
 * ScienceData one-time welcome + terms-consent modal (vanilla JS).
 *
 * Loaded by WelcomeListener only for logged-in users who haven't acknowledged
 * yet, so it just renders unconditionally. "Continue" = passive consent: it POSTs
 * to the OCS api#setWelcomed endpoint, which sets the per-user 'welcomed' flag so
 * this never shows again. If the POST fails the modal is still dismissed and
 * simply reappears next page load (no consent recorded), so it degrades safely.
 */
(function () {
	'use strict';

	if (document.getElementById('sd-welcome-overlay')) {
		return;
	}

	// The terms live as a picocms site; keep it a plain path so it works behind
	// the /sites rewrite (and is editable by the 'cloud' account).
	var TERMS_URL = '/sites/terms';

	function build() {
		var overlay = document.createElement('div');
		overlay.id = 'sd-welcome-overlay';
		overlay.className = 'sd-welcome-overlay';

		var card = document.createElement('div');
		card.className = 'sd-welcome-card';
		card.setAttribute('role', 'dialog');
		card.setAttribute('aria-modal', 'true');
		card.setAttribute('aria-labelledby', 'sd-welcome-title');

		var h = document.createElement('h2');
		h.id = 'sd-welcome-title';
		h.textContent = 'Welcome to ScienceData';

		var p = document.createElement('p');
		p.textContent = 'A non-profit research data service run by i2 at the Technical University of Denmark. Store, share, compute on, and publish your data.';

		var terms = document.createElement('p');
		terms.className = 'sd-welcome-terms';
		terms.appendChild(document.createTextNode('By continuing, you agree to our '));
		var a = document.createElement('a');
		a.href = TERMS_URL;
		a.target = '_blank';
		a.rel = 'noopener';
		a.textContent = 'Terms of Service';
		terms.appendChild(a);
		terms.appendChild(document.createTextNode('.'));

		var btn = document.createElement('button');
		btn.className = 'sd-welcome-btn';
		btn.type = 'button';
		btn.textContent = 'Continue';

		card.appendChild(h);
		card.appendChild(p);
		card.appendChild(terms);
		card.appendChild(btn);
		overlay.appendChild(card);
		document.body.appendChild(overlay);

		btn.addEventListener('click', function () {
			btn.disabled = true;
			var base = (window.OC && OC.linkToOCS)
				? OC.linkToOCS('apps/files_picocms/api/v1', 2)
				: '/ocs/v2.php/apps/files_picocms/api/v1/';
			fetch(base + 'welcomed', {
				method: 'POST',
				headers: {
					requesttoken: (window.OC && OC.requestToken) ? OC.requestToken : '',
					'OCS-APIRequest': 'true',
					Accept: 'application/json',
				},
			}).then(dismiss, dismiss);
		});
	}

	function dismiss() {
		var el = document.getElementById('sd-welcome-overlay');
		if (el && el.parentNode) {
			el.parentNode.removeChild(el);
		}
	}

	if (document.body) {
		build();
	} else {
		document.addEventListener('DOMContentLoaded', build);
	}
})();
