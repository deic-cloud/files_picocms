/* global OC, t */

(function () {
	'use strict';

	const OCS = OC.generateUrl('/ocs/v2.php/apps/files_picocms/api/v1');

	document.addEventListener('DOMContentLoaded', function () {
		document.getElementById('sampleDirSubmit')?.addEventListener('click', async function () {
			const owner = document.getElementById('sampleDirOwner').value;
			const path  = document.getElementById('sampleDirPath').value;
			const msg   = document.getElementById('ownerChange');

			const res = await fetch(OCS + '/sample-folder', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
					'OCS-APIREQUEST': 'true',
					'requesttoken': OC.requestToken,
				},
				body: new URLSearchParams({ owner, path }).toString(),
			});
			const data = await res.json();
			if (msg) {
				msg.textContent = data?.ocs?.meta?.status === 'ok'
					? t('files_picocms', 'Saved.')
					: t('files_picocms', 'Error saving.');
			}
		});
	});
})();
