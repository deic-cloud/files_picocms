/* global OC, t */

(function () {
	'use strict';

	const OCS = OC.generateUrl('/ocs/v2.php/apps/files_picocms/api/v1');

	async function ocsPost(path, body) {
		const res = await fetch(OCS + path, {
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

	async function ocsDelete(path, params) {
		const url = OCS + path + (params ? '?' + new URLSearchParams(params).toString() : '');
		const res = await fetch(url, {
			method: 'DELETE',
			headers: { 'OCS-APIREQUEST': 'true', 'requesttoken': OC.requestToken },
		});
		return res.json();
	}

	// ── Row helpers ──────────────────────────────────────────────────────────────

	function buildSiteRow(path, name) {
		const root = OC.webroot || '';
		const tr   = document.createElement('tr');
		tr.className = 'picoSiteRow';
		tr.dataset.path = path;
		tr.innerHTML = `
			<td><a href="${root}/index.php/apps/files?dir=${encodeURIComponent(path)}">${path}</a></td>
			<td><input class="picoSiteName" type="text" value="${name}" title="${t('files_picocms', 'Edit to rename')}" /></td>
			<td><a href="${root}/remote.php/files_picocms/sites/${encodeURIComponent(name)}" target="_blank" rel="noopener">/remote.php/files_picocms/sites/${name}</a></td>
			<td class="picoActions">
				<button class="picoManageBtn" data-path="${path}">${t('files_picocms', 'Manage')}</button>
				<button class="picoDeleteBtn" data-path="${path}">${t('files_picocms', 'Remove')}</button>
			</td>`;
		bindRow(tr);
		return tr;
	}

	function bindRow(tr) {
		const nameInput = tr.querySelector('.picoSiteName');
		const path      = tr.dataset.path;

		nameInput?.addEventListener('change', async function () {
			const name = this.value.trim();
			if (!name) return;
			const data = await ocsPost('/sites', { folder: path, name, rename: 'yes' });
			if (data?.ocs?.meta?.status !== 'ok') {
				alert(t('files_picocms', 'Could not rename site.'));
				return;
			}
			// Update the link cell
			const root = OC.webroot || '';
			const link = tr.querySelector('td:nth-child(3) a');
			if (link) {
				link.href = `${root}/remote.php/files_picocms/sites/${encodeURIComponent(name)}`;
				link.textContent = `/remote.php/files_picocms/sites/${name}`;
			}
		});

		tr.querySelector('.picoManageBtn')?.addEventListener('click', function () {
			// Open NC Files at the site folder for now; future: open manage popup
			window.location.href = (OC.webroot || '') + '/index.php/apps/files?dir=' + encodeURIComponent(path);
		});

		tr.querySelector('.picoDeleteBtn')?.addEventListener('click', async function () {
			if (!confirm(t('files_picocms', 'Stop serving folder: ') + path + '?')) return;
			const data = await ocsDelete('/sites', { folder: path });
			if (data?.ocs?.meta?.status === 'ok') {
				tr.remove();
				maybeShowEmpty();
			} else {
				alert(t('files_picocms', 'Could not remove site.'));
			}
		});
	}

	function maybeShowEmpty() {
		const empty = document.getElementById('picoNoSites');
		if (!empty) return;
		const rows = document.querySelectorAll('#picoSiteList .picoSiteRow');
		empty.style.display = rows.length === 0 ? '' : 'none';
	}

	// ── Wizard ───────────────────────────────────────────────────────────────────

	function initWizard() {
		const dialog      = document.getElementById('picoWizardDialog');
		const folderInput = document.getElementById('picoWizardFolder');
		const msg         = document.getElementById('picoWizardMsg');

		document.getElementById('picoNewSiteBtn')?.addEventListener('click', () => {
			dialog.style.display = '';
		});

		document.getElementById('picoWizardCancel')?.addEventListener('click', () => {
			dialog.style.display = 'none';
			if (msg) msg.textContent = '';
		});

		// Close on backdrop click
		dialog?.addEventListener('click', (e) => {
			if (e.target === dialog) {
				dialog.style.display = 'none';
				if (msg) msg.textContent = '';
			}
		});

		// Update folder suggestion when type changes
		document.querySelectorAll('input[name="pico_type"]').forEach(radio => {
			radio.addEventListener('change', function () {
				if (folderInput) folderInput.value = this.dataset.folder || '';
			});
		});

		document.getElementById('picoWizardCreate')?.addEventListener('click', async () => {
			const selected = document.querySelector('input[name="pico_type"]:checked');
			if (!selected) return;
			const folder      = folderInput?.value.trim() || selected.dataset.folder;
			const content     = selected.dataset.content     || '';
			const destination = selected.dataset.destination || '';
			const theme       = selected.dataset.theme       || '';
			const copyThemes  = selected.dataset.copyThemes  || 'no';
			const name        = folder.replace(/.*\//, '') || folder;

			if (msg) msg.textContent = t('files_picocms', 'Creating…');

			const data = await ocsPost('/create', { folder, name, content, destination, theme, copy_themes: copyThemes });

			if (data?.ocs?.meta?.status === 'ok') {
				const siteName = data.ocs.data.site;
				dialog.style.display = 'none';
				if (msg) msg.textContent = '';

				// Add row to table
				const tbody = document.getElementById('picoSiteList');
				if (tbody) tbody.appendChild(buildSiteRow(folder, siteName));
				maybeShowEmpty();
			} else {
				const err = data?.ocs?.data?.error || t('files_picocms', 'Unexpected error.');
				if (msg) msg.textContent = err;
			}
		});
	}

	// ── Register existing folder ─────────────────────────────────────────────────

	function initAddManual() {
		document.getElementById('picoAddPathBrowse')?.addEventListener('click', () => {
			if (!window.OC?.dialogs?.filepicker) return;
			OC.dialogs.filepicker(
				t('files_picocms', 'Choose folder'),
				(path) => { document.getElementById('picoAddPath').value = path || '/'; },
				false, 'httpd/unix-directory', true,
				OC.dialogs.FILEPICKER_TYPE_CHOOSE
			);
		});

		document.getElementById('picoAddBtn')?.addEventListener('click', async () => {
			const folder = document.getElementById('picoAddPath')?.value.trim();
			const name   = document.getElementById('picoAddName')?.value.trim();
			if (!folder || !name) return;

			const data = await ocsPost('/sites', { folder, name });
			if (data?.ocs?.meta?.status === 'ok') {
				const tbody = document.getElementById('picoSiteList');
				if (tbody) tbody.appendChild(buildSiteRow(folder, name));
				document.getElementById('picoAddPath').value = '';
				document.getElementById('picoAddName').value = '';
				maybeShowEmpty();
			} else {
				alert(t('files_picocms', 'Name already taken or folder invalid.'));
			}
		});
	}

	// ── Public page toggle ───────────────────────────────────────────────────────

	function initPublicToggle() {
		document.getElementById('picoServePublic')?.addEventListener('change', async function () {
			const serve = this.checked ? 'yes' : 'no';
			await ocsPost('/serve-public', { serve });
			// Simple reload to update the "Your public page" link
			if (this.checked) window.location.reload();
		});
	}

	// ── Init ─────────────────────────────────────────────────────────────────────

	document.addEventListener('DOMContentLoaded', function () {
		// Bind existing rows
		document.querySelectorAll('#picoSiteList .picoSiteRow').forEach(bindRow);
		maybeShowEmpty();
		initWizard();
		initAddManual();
		initPublicToggle();
	});

})();
