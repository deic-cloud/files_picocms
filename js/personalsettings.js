/* global OC, t */

(function () {
	'use strict';

	const OCS = (OC.webroot || '') + '/ocs/v2.php/apps/files_picocms/api/v1';

	async function ocsPost(path, body) {
		const res = await fetch(OCS + path + '?format=json', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
				'OCS-APIREQUEST': 'true',
				'requesttoken': OC.requestToken,
			},
			body: new URLSearchParams(body).toString(),
		});
		return res.json();
	}

	async function ocsGet(path, params) {
		const p = new URLSearchParams({ ...(params || {}), format: 'json' });
		const res = await fetch(OCS + path + '?' + p.toString(), {
			headers: { 'OCS-APIREQUEST': 'true', 'requesttoken': OC.requestToken },
		});
		return res.json();
	}

	async function ocsDelete(path, params) {
		const p = new URLSearchParams({ ...(params || {}), format: 'json' });
		const res = await fetch(OCS + path + '?' + p.toString(), {
			method: 'DELETE',
			headers: { 'OCS-APIREQUEST': 'true', 'requesttoken': OC.requestToken },
		});
		return res.json();
	}

	function addSiteRow(folder, name) {
		const serverRoot = OC.webroot || '';
		const row = document.createElement('div');
		row.className = 'siteFolder';
		row.dataset.path = folder;
		row.innerHTML = `
			<span class="folder">
				<a href="${serverRoot}/index.php/apps/files/?dir=${encodeURIComponent(folder)}">
					<label>${folder}</label>
				</a>
			</span>
			<span class="url">
				<label>${serverRoot}/remote.php/files_picocms/sites/</label>
				<input type="text" value="${name}" autocomplete="off" />
			</span>
			<button class="remove-site-btn" data-path="${folder}">-</button>`;
		document.getElementById('filesPicoSiteFoldersList').appendChild(row);
		bindRemoveBtn(row.querySelector('.remove-site-btn'));
		bindRenameInput(row.querySelector('input[type="text"]'), folder);
	}

	function bindRemoveBtn(btn) {
		btn.addEventListener('click', function () {
			const path = this.dataset.path;
			const row  = this.closest('.siteFolder');
			OC.dialogs.confirm(
				t('files_picocms', 'Stop serving folder: ') + path + '?',
				t('files_picocms', 'Remove site'),
				async function (confirmed) {
					if (!confirmed) return;
					const data = await ocsDelete('/sites', { folder: path });
					if (data?.ocs?.meta?.status === 'ok') {
						row.remove();
					} else {
						alert(t('files_picocms', 'Could not remove site folder.'));
					}
				},
				true
			);
		});
	}

	function bindRenameInput(input, folder) {
		input.addEventListener('change', async function () {
			const name = this.value.trim();
			if (!name) return;
			const data = await ocsPost('/sites', { folder, name, rename: 'yes' });
			if (data?.ocs?.meta?.status !== 'ok') {
				alert(t('files_picocms', 'Could not rename site.'));
			}
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		// Bind existing remove buttons
		document.querySelectorAll('.remove-site-btn').forEach(bindRemoveBtn);

		// Bind existing rename inputs
		document.querySelectorAll('#filesPicoSiteFoldersList .siteFolder').forEach(function (row) {
			const input = row.querySelector('input[type="text"]');
			const path  = row.dataset.path;
			if (input && path) bindRenameInput(input, path);
		});

		// Add site folder
		document.getElementById('addSiteFolderBtn')?.addEventListener('click', async function () {
			const folder = document.getElementById('newSiteFolderPath').value.trim();
			if (!folder) return;
			const name = folder.replace(/.*\//, '');
			const data = await ocsPost('/sites', { folder, name });
			if (data?.ocs?.meta?.status === 'ok') {
				addSiteRow(folder, name);
				document.getElementById('newSiteFolderPath').value = '';
			} else {
				alert(t('files_picocms', 'Name already taken. Please choose another.'));
			}
		});

		// Serve public URL toggle
		document.getElementById('servePublicUrl')?.addEventListener('change', async function () {
			const serve = this.checked ? 'yes' : 'no';
			await ocsPost('/serve-public', { serve });
		});

		// Website wizard
		const wizardBtn    = document.getElementById('picoWizardBtn');
		const wizardDialog = document.getElementById('picoWizardDialog');
		const wizardFolder = document.getElementById('wizardFolder');

		wizardBtn?.addEventListener('click', function () {
			wizardDialog.style.display = wizardDialog.style.display === 'none' ? '' : 'none';
		});

		document.querySelectorAll('input[name="pico_type"]').forEach(function (radio) {
			radio.addEventListener('change', function () {
				if (wizardFolder) {
					wizardFolder.value = this.dataset.folder || '';
				}
			});
		});

		document.getElementById('picoWizardCancel')?.addEventListener('click', function () {
			wizardDialog.style.display = 'none';
		});

		document.getElementById('picoWizardCreate')?.addEventListener('click', async function () {
			const selected = document.querySelector('input[name="pico_type"]:checked');
			if (!selected) return;
			const folder      = wizardFolder?.value.trim() || selected.dataset.folder;
			const content     = selected.dataset.content     || '';
			const destination = selected.dataset.destination || '';
			const theme       = selected.dataset.theme       || '';
			const copyThemes  = selected.dataset.copyThemes  || 'no';
			const name        = folder.replace(/.*\//, '') || folder;
			const msg         = document.getElementById('picoWizardMsg');
			if (msg) msg.textContent = t('files_picocms', 'Creating…');
			const data = await ocsPost('/create', { folder, name, content, destination, theme, copy_themes: copyThemes });
			if (data?.ocs?.meta?.status === 'ok') {
				const site = data.ocs.data.site;
				const url  = (OC.webroot || '') + '/remote.php/files_picocms/sites/' + encodeURIComponent(site);
				if (msg) msg.textContent = '';
				wizardDialog.style.display = 'none';
				window.location.href = url;
			} else {
				const err = data?.ocs?.data?.error || t('files_picocms', 'Unexpected error.');
				if (msg) msg.textContent = err;
			}
		});
	});
})();
