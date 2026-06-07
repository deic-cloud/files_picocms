/* global OCA, OC, t */

(function () {
	'use strict';

	var SIDEBAR_TAG = 'files-picocms-sidebar';

	var GLOBE_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">' +
		'<path d="M17.9 17.39c-.26-.8-1.01-1.39-1.9-1.39h-1v-3a1 1 0 0 0-1-1H8v-2h2a1 1 0 0 0 ' +
		'1-1V7h2a2 2 0 0 0 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39M11 19.93c-3.95-.49-7-3.85-7-7.93 ' +
		'0-.62.08-1.21.21-1.79L9 15v1a2 2 0 0 0 2 2m1-16A10 10 0 0 0 2 12a10 10 0 0 0 10 10 10 10 0 0 0 10-10A10 10 0 0 0 12 4Z"/>' +
		'</svg>';

	var PENCIL_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">' +
		'<path d="M20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.84 ' +
		'1.83 3.75 3.75M3 17.25V21h3.75L17.81 9.93l-3.75-3.75L3 17.25Z"/>' +
		'</svg>';

	// ── OCS API ───────────────────────────────────────────────────────────────

	function ocsHeaders() {
		return {
			'OCS-APIREQUEST': 'true',
			'requesttoken': OC.requestToken,
			'Accept': 'application/json',
		};
	}

	function ocsBase() {
		// Construct directly — OC.generateUrl adds index.php which breaks OCS routing
		return (OC.webroot || '') + '/ocs/v2.php/apps/files_picocms/api/v1';
	}

	function ocsGet(path) {
		return fetch(ocsBase() + path, { headers: ocsHeaders() }).then(function(r) { return r.json(); });
	}

	function ocsPost(path, body) {
		return fetch(ocsBase() + path, {
			method: 'POST',
			headers: Object.assign({ 'Content-Type': 'application/x-www-form-urlencoded' }, ocsHeaders()),
			body: new URLSearchParams(body).toString(),
		}).then(function(r) { return r.json(); });
	}

	function ocsDelete(path, params) {
		var url = ocsBase() + path + (params ? '?' + new URLSearchParams(params).toString() : '');
		return fetch(url, { method: 'DELETE', headers: ocsHeaders() }).then(function(r) { return r.json(); });
	}

	// ── Node helpers ──────────────────────────────────────────────────────────

	function getPath(node) {
		if (!node) return '';
		if (typeof node.path === 'string') return node.path;
		if (typeof node.get === 'function') return node.get('path') || '';
		return '';
	}

	function isDir(node) {
		if (!node) return false;
		// NC34 @nextcloud/files uses 'folder'; older NC uses 'dir'
		if (node.type === 'folder' || node.type === 'dir') return true;
		if (node.mime === 'httpd/unix-directory') return true;
		if (typeof node.isDirectory === 'function' && node.isDirectory()) return true;
		if (typeof node.get === 'function') {
			var t = node.get('type'), m = node.get('mimetype');
			return t === 'folder' || t === 'dir' || m === 'httpd/unix-directory';
		}
		return false;
	}

	// ── Panel rendering ───────────────────────────────────────────────────────

	function renderPanel(el, folderPath) {
		el.innerHTML = '<div class="picocms-loading">' + t('files_picocms', 'Loading…') + '</div>';

		ocsGet('/sites').then(function(data) {
			var sites   = (data && data.ocs && data.ocs.data) ? data.ocs.data : [];
			var site    = null;
			for (var i = 0; i < sites.length; i++) {
				if (sites[i].path === folderPath) { site = sites[i]; break; }
			}

			var root    = OC.webroot || '';
			var appUrl  = OC.generateUrl('/apps/files_picocms');
			var siteUrl = site ? root + '/remote.php/files_picocms/sites/' + encodeURIComponent(site.site) : '';
			var checked = site ? ' checked' : '';

			el.innerHTML =
				'<div class="picocms-panel">' +
					'<div class="picocms-serve-row">' +
						'<label class="picocms-serve-label">' +
							'<input type="checkbox" class="picocms-serve-cb"' + checked + ' />' +
							'<span>' + t('files_picocms', 'Serve as website') + '</span>' +
						'</label>' +
						(site
							? '<a href="' + appUrl + '" class="picocms-edit-icon" title="' + t('files_picocms', 'Manage websites') + '">' + PENCIL_SVG + '</a>'
							: '') +
					'</div>' +
					(site
						? '<div class="picocms-url"><a href="' + siteUrl + '" target="_blank" rel="noopener">' + siteUrl + '</a></div>'
						: '') +
					'<p class="picocms-msg"></p>' +
				'</div>';

			var cb  = el.querySelector('.picocms-serve-cb');
			var msg = el.querySelector('.picocms-msg');

			if (!cb) return;

			cb.addEventListener('change', function () {
				cb.disabled = true;
				msg.textContent = '';

				if (cb.checked) {
					var name = folderPath.split('/').filter(Boolean).pop() || 'site';
					ocsPost('/sites', { folder: folderPath, name: name }).then(function(res) {
						if (res && res.ocs && res.ocs.meta && res.ocs.meta.status === 'ok') {
							renderPanel(el, folderPath);
						} else {
							cb.checked = false;
							cb.disabled = false;
							msg.textContent = t('files_picocms', 'Could not register site.');
						}
					}).catch(function() {
						cb.checked = false;
						cb.disabled = false;
						msg.textContent = t('files_picocms', 'Could not register site.');
					});
				} else {
					ocsDelete('/sites', { folder: folderPath }).then(function(res) {
						if (res && res.ocs && res.ocs.meta && res.ocs.meta.status === 'ok') {
							renderPanel(el, folderPath);
						} else {
							cb.checked = true;
							cb.disabled = false;
							msg.textContent = t('files_picocms', 'Could not remove site.');
						}
					}).catch(function() {
						cb.checked = true;
						cb.disabled = false;
						msg.textContent = t('files_picocms', 'Could not remove site.');
					});
				}
			});
		}).catch(function(err) {
			console.error('[files_picocms] sidebar fetch error:', err);
			el.innerHTML = '<div class="picocms-loading">' + t('files_picocms', 'Error loading site info.') + '</div>';
		});
	}

	// ── Custom element (NC31+) ────────────────────────────────────────────────

	class PicoCMSSidebarElement extends HTMLElement {
		constructor() {
			super();
			this._active = false;
			this._node   = null;
			this._path   = null;
		}

		set active(v) { this._active = !!v; this._maybeRender(); }
		set node(v)   { this._node = v; this._path = null; this._maybeRender(); }
		set folder(v) {}
		set view(v)   {}

		connectedCallback() { this._maybeRender(); }

		_maybeRender() {
			if (!this._active || !this._node || !this.isConnected) return;
			if (!isDir(this._node)) { this.innerHTML = ''; return; }
			var path = getPath(this._node);
			if (!path || path === this._path) return;
			this._path = path;
			renderPanel(this, path);
		}
	}

	function defineCustomElement() {
		if (!window.customElements.get(SIDEBAR_TAG)) {
			window.customElements.define(SIDEBAR_TAG, PicoCMSSidebarElement);
		}
	}

	// ── Tab definition ────────────────────────────────────────────────────────

	function makeTabDef() {
		var label     = t('files_picocms', 'Website');
		var mountedEl = null;
		var mountedPath = null;

		return {
			id:            'picocms',
			name:          label,
			displayName:   label,
			iconSvgInline: GLOBE_SVG,
			order:         60,
			tagName:       SIDEBAR_TAG,

			enabled: function(nodes) {
				if (!nodes || !nodes.length) return true;
				return isDir(nodes[0]);
			},

			// NC30 API
			mount: function(el, fileInfo) {
				mountedEl   = el;
				mountedPath = getPath(fileInfo);
				renderPanel(el, mountedPath);
			},
			onMount: function(el, fileInfo) {
				mountedEl   = el;
				mountedPath = getPath(fileInfo);
				renderPanel(el, mountedPath);
			},
			update: function(fileInfo) {
				mountedPath = getPath(fileInfo);
				if (mountedEl) renderPanel(mountedEl, mountedPath);
			},
			destroy: function() {
				if (mountedEl) mountedEl.innerHTML = '';
				mountedEl = null;
			},
			setIsActive: function(active) {
				if (active && mountedEl) renderPanel(mountedEl, mountedPath);
			},
			setActive:   function() {},
			setFileInfo: function(fi) {
				if (mountedEl) renderPanel(mountedEl, getPath(fi));
			},

			// NC31+
			onInit: function() { return Promise.resolve(defineCustomElement()); },
		};
	}

	// ── Register ──────────────────────────────────────────────────────────────

	var _registered = false;

	function tryRegister(attemptsLeft) {
		if (_registered) return;

		if (OCA && OCA.Files && OCA.Files.Sidebar && typeof OCA.Files.Sidebar.registerTab === 'function') {
			defineCustomElement();
			OCA.Files.Sidebar.registerTab(makeTabDef());
			_registered = true;
			return;
		}

		if (window._nc_files_scope) {
			var keys = Object.keys(window._nc_files_scope);
			for (var i = 0; i < keys.length; i++) {
				var candidate = window._nc_files_scope[keys[i]];
				if (candidate && typeof candidate === 'object') {
					defineCustomElement();
					var tabs = new Map(candidate.filesSidebarTabs || []);
					tabs.set('picocms', makeTabDef());
					candidate.filesSidebarTabs = tabs;
					_registered = true;
					return;
				}
			}
		}

		if (attemptsLeft > 0) {
			setTimeout(function() { tryRegister(attemptsLeft - 1); }, 250);
		}
	}

	document.addEventListener('DOMContentLoaded', function() { tryRegister(20); });

})();
