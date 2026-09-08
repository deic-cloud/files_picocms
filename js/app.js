/* global OC, t */

(function () {
	'use strict';

	const OCS = (OC.webroot || '') + '/ocs/v2.php/apps/files_picocms/api/v1';
	// Site URL prefix — '' when the web server rewrites /sites|/users to remote.php
	const URL_PREFIX = document.getElementById('picocms-app')?.dataset.urlPrefix ?? '/remote.php/files_picocms';
	// Published links point at the master (it redirects to the hosting silo)
	const LINK_BASE = document.getElementById('picocms-app')?.dataset.linkBase ?? '';

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
		return parseOcs(res);
	}

	async function ocsGet(path, params) {
		const p = new URLSearchParams({ ...(params || {}), format: 'json' });
		const res = await fetch(OCS + path + '?' + p.toString(), {
			headers: { 'OCS-APIREQUEST': 'true', 'requesttoken': OC.requestToken },
		});
		return parseOcs(res);
	}

	// Some failures (proxy/method errors) return HTML or an empty body —
	// res.json() would throw an uncaught SyntaxError. Parse defensively and
	// surface the HTTP status instead.
	async function parseOcs(res) {
		const text = await res.text();
		try { return JSON.parse(text); } catch (e) {
			return { ocs: { meta: { status: 'error', statuscode: res.status, message: 'HTTP ' + res.status } } };
		}
	}

	async function ocsPut(path, params) {
		const res = await fetch(OCS + path + '?format=json', {
			method: 'PUT',
			headers: { 'OCS-APIREQUEST': 'true', 'requesttoken': OC.requestToken, 'Content-Type': 'application/json' },
			body: JSON.stringify(params || {}),
		});
		return parseOcs(res);
	}

	async function ocsDelete(path, params) {
		const p = new URLSearchParams({ ...(params || {}), format: 'json' });
		const res = await fetch(OCS + path + '?' + p.toString(), {
			method: 'DELETE',
			headers: { 'OCS-APIREQUEST': 'true', 'requesttoken': OC.requestToken },
		});
		return parseOcs(res);
	}

	// ── Row helpers ──────────────────────────────────────────────────────────────

	function buildSiteRow(path, name) {
		const root = OC.webroot || '';
		const tr   = document.createElement('tr');
		tr.className = 'picoSiteRow';
		tr.dataset.path = path;
		tr.innerHTML = `
			<td class="picoFolderCell">
				<input class="picoSitePath" type="text" value="${path}" title="${t('files_picocms', 'Folder served — edit or browse to move the site')}" />
				<button class="picoPathBrowseRow button" title="${t('files_picocms', 'Choose folder')}">…</button>
				<a class="picoFilesLink" href="${root}/index.php/apps/files?dir=${encodeURIComponent(path)}" target="_blank" rel="noopener" title="${t('files_picocms', 'Browse site files in Nextcloud')}">↗</a>
			</td>
			<td><input class="picoSiteName" type="text" value="${name}" title="${t('files_picocms', 'URL slug — edit to rename')}" /></td>
			<td><a href="${LINK_BASE}${URL_PREFIX}/sites/${encodeURIComponent(name)}" target="_blank" rel="noopener" title="${t('files_picocms', 'Open the website in a new tab')}">${LINK_BASE}${URL_PREFIX}/sites/${name}</a></td>
			<td class="picoActions">
				<button class="picoManageBtn" data-path="${path}" title="${t('files_picocms', 'Edit site configuration (_config.md)')}">${t('files_picocms', 'Manage')}</button>
				<button class="picoDeleteBtn" data-path="${path}" title="${t('files_picocms', 'Stop serving this folder as a website')}">${t('files_picocms', 'Remove')}</button>
			</td>`;
		bindRow(tr);
		return tr;
	}

	function bindRow(tr) {
		const nameInput = tr.querySelector('.picoSiteName');
		const pathInput = tr.querySelector('.picoSitePath');

		nameInput?.addEventListener('change', async function () {
			const name = this.value.trim();
			if (!name) return;
			const data = await ocsPost('/sites', { folder: tr.dataset.path, name, rename: 'yes' });
			if (data?.ocs?.meta?.status !== 'ok') {
				alert(t('files_picocms', 'Could not rename site.'));
				return;
			}
			// Update the link cell
			const root = OC.webroot || '';
			const link = tr.querySelector('td:nth-child(3) a');
			if (link) {
				link.href = `${LINK_BASE}${URL_PREFIX}/sites/${encodeURIComponent(name)}`;
				link.textContent = `${LINK_BASE}${URL_PREFIX}/sites/${name}`;
			}
		});

		// Editable folder: change or browse → move the site to another folder.
		async function moveTo(newPath) {
			newPath = (newPath || '').trim();
			if (!newPath || newPath === tr.dataset.path) return;
			const name = nameInput?.value.trim();
			const data = await ocsPut('/sites', { name, folder: newPath });
			if (data?.ocs?.meta?.status !== 'ok') {
				const why = data?.ocs?.meta?.message || data?.ocs?.data?.error || '';
				alert(t('files_picocms', 'Could not move the site') + (why ? ' — ' + why : ''));
				if (pathInput) pathInput.value = tr.dataset.path;
				return;
			}
			tr.dataset.path = newPath;
			if (pathInput) pathInput.value = newPath;
			const filesLink = tr.querySelector('.picoFilesLink');
			if (filesLink) filesLink.href = `${OC.webroot || ''}/index.php/apps/files?dir=${encodeURIComponent(newPath)}`;
			tr.querySelectorAll('[data-path]').forEach((el) => { el.dataset.path = newPath; });
		}
		pathInput?.addEventListener('change', function () { moveTo(this.value); });
		tr.querySelector('.picoPathBrowseRow')?.addEventListener('click', function () {
			if (!window.OC?.dialogs?.filepicker) return;
			OC.dialogs.filepicker(
				t('files_picocms', 'Choose folder'),
				(p) => moveTo(p || '/'),
				false, 'httpd/unix-directory', true,
				OC.dialogs.FILEPICKER_TYPE_CHOOSE
			);
		});

		tr.querySelector('.picoManageBtn')?.addEventListener('click', function () {
			openConfigEditor(tr.dataset.path);
		});

		tr.querySelector('.picoDeleteBtn')?.addEventListener('click', function () {
			const path = tr.dataset.path;
			OC.dialogs.confirm(
				t('files_picocms', 'Stop serving folder: ') + path + '?',
				t('files_picocms', 'Remove site'),
				async function (confirmed) {
					if (!confirmed) return;
					const data = await ocsDelete('/sites', { folder: path });
					if (data?.ocs?.meta?.status === 'ok') {
						tr.remove();
						maybeShowEmpty();
					} else {
						alert(t('files_picocms', 'Could not remove site.'));
					}
				},
				true
			);
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

		// 'website wizard' link in the public-page hint opens the same dialog
		document.getElementById('picoHintWizard')?.addEventListener('click', (e) => {
			e.preventDefault();
			dialog.style.display = '';
			dialog.scrollIntoView({ block: 'center' });
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

		// Update folder suggestion when type changes. The public profile page is
		// fixed to /public (that is the folder the personal URL serves), so the
		// destination field is locked for it.
		const applyTypeToFolder = (radio) => {
			const locked = radio.value === 'blog-profile';
			if (folderInput) {
				folderInput.value = radio.dataset.folder || '';
				folderInput.disabled = locked;
			}
			const browse = document.getElementById('picoWizardFolderBrowse');
			if (browse) browse.disabled = locked;
		};
		document.querySelectorAll('input[name="pico_type"]').forEach(radio => {
			radio.addEventListener('change', function () { applyTypeToFolder(this); });
		});
		{
			const checked = document.querySelector('input[name="pico_type"]:checked');
			if (checked) applyTypeToFolder(checked);
		}

		document.getElementById('picoWizardFolderBrowse')?.addEventListener('click', () => {
			if (!window.OC?.dialogs?.filepicker) return;
			OC.dialogs.filepicker(
				t('files_picocms', 'Choose folder'),
				(path) => { if (folderInput) folderInput.value = path || '/'; },
				false, 'httpd/unix-directory', true,
				OC.dialogs.FILEPICKER_TYPE_CHOOSE
			);
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
		const addBtn  = document.getElementById('picoAddBtn');
		const addPath = document.getElementById('picoAddPath');
		const addName = document.getElementById('picoAddName');

		function updateServeBtn() {
			if (addBtn) addBtn.disabled = !addPath?.value.trim() || !addName?.value.trim();
		}
		// Default the site name to the chosen folder's name — unless the user
		// typed one, or that name is already taken by a listed site.
		let nameTouched = false;
		function takenNames() {
			return Array.prototype.map.call(document.querySelectorAll('.picoSiteName'), (i) => i.value.trim());
		}
		function suggestName() {
			if (nameTouched || !addName) return;
			const base = (addPath?.value.trim() || '').replace(/\/+$/, '').split('/').pop() || '';
			addName.value = (base && takenNames().indexOf(base) === -1) ? base : '';
			updateServeBtn();
		}
		addPath?.addEventListener('input', () => { suggestName(); updateServeBtn(); });
		addPath?.addEventListener('change', () => { suggestName(); updateServeBtn(); });
		addName?.addEventListener('input', function () { nameTouched = this.value.trim() !== ''; updateServeBtn(); });

		document.getElementById('picoAddPathBrowse')?.addEventListener('click', () => {
			if (!window.OC?.dialogs?.filepicker) return;
			OC.dialogs.filepicker(
				t('files_picocms', 'Choose folder'),
				(path) => { if (addPath) addPath.value = path || '/'; suggestName(); updateServeBtn(); },
				false, 'httpd/unix-directory', true,
				OC.dialogs.FILEPICKER_TYPE_CHOOSE
			);
		});

		addBtn?.addEventListener('click', async () => {
			const folder = addPath?.value.trim();
			const name   = addName?.value.trim();
			if (!folder || !name) return;

			const data = await ocsPost('/sites', { folder, name });
			if (data?.ocs?.meta?.status === 'ok') {
				const tbody = document.getElementById('picoSiteList');
				if (tbody) tbody.appendChild(buildSiteRow(folder, name));
				addPath.value = '';
				addName.value = '';
				updateServeBtn();
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
			// Reload to update the public-page link state (live vs greyed)
			window.location.reload();
		});
	}

	// ── Config editor ────────────────────────────────────────────────────────────

	async function openConfigEditor(path) {
		const dialog   = document.getElementById('picoConfigDialog');
		const textarea = document.getElementById('picoConfigContent');
		const msg      = document.getElementById('picoConfigMsg');
		const titleEl  = document.getElementById('picoConfigTitle');

		if (!dialog || !textarea) return;
		if (titleEl) titleEl.textContent = '…';
		if (msg) msg.textContent = '';
		textarea.value = '';
		dialog.dataset.path = path;
		dialog.style.display = '';

		const data = await ocsGet('/config', { folder: path });
		if (data?.ocs?.meta?.status === 'ok') {
			textarea.value = data.ocs.data.content || '';
			if (titleEl) titleEl.textContent = data.ocs.data.file || path;
		} else {
			if (msg) msg.textContent = t('files_picocms', 'Could not load config.');
		}
	}

	function initConfigEditor() {
		const dialog   = document.getElementById('picoConfigDialog');
		const textarea = document.getElementById('picoConfigContent');
		const msg      = document.getElementById('picoConfigMsg');

		document.getElementById('picoConfigSave')?.addEventListener('click', async () => {
			if (msg) msg.textContent = t('files_picocms', 'Saving…');
			const path    = dialog.dataset.path;
			const content = textarea?.value || '';
			const data    = await ocsPost('/config', { folder: path, content });
			if (data?.ocs?.meta?.status === 'ok') {
				if (msg) msg.textContent = t('files_picocms', 'Saved.');
			} else {
				if (msg) msg.textContent = t('files_picocms', 'Could not save config.');
			}
		});

		document.getElementById('picoConfigCancel')?.addEventListener('click', () => {
			dialog.style.display = 'none';
			if (msg) msg.textContent = '';
		});

		dialog?.addEventListener('click', (e) => {
			if (e.target === dialog) {
				dialog.style.display = 'none';
				if (msg) msg.textContent = '';
			}
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
		initConfigEditor();
	});

})();
