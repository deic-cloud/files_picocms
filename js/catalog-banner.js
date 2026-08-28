/**
 * Slim catalog top bar on public share pages of catalog-listed shares — the
 * way back to the repository listing (same-tab, Zenodo-style). Injected only
 * when CatalogBannerListener matched; state carries the catalog URL + brand.
 */
(function () {
	'use strict'
	var state
	try {
		state = OCP.InitialState.loadState('files_picocms', 'catalog_banner')
	} catch (e) { return }
	if (!state || !state.url) { return }

	function insert() {
		if (document.getElementById('sdCatalogBanner')) { return }
		var bar = document.createElement('div')
		bar.id = 'sdCatalogBanner'
		// z-index BELOW NC modals (viewer overlay ~10000+): while a file viewer is
		// open it covers the banner — proper modal semantics — and the viewer's own
		// close X (top-right, which the 9999 banner used to swallow) is visible.
		bar.style.cssText = 'position:fixed;top:0;left:0;right:0;height:40px;z-index:1500;'
			+ 'display:flex;align-items:center;gap:10px;padding:0 16px;'
			+ 'background:#1b456d;color:#fff;font-size:13px;'
		// Breadcrumb (Zenodo-style): BRAND › LABEL — brand to the front page,
		// label to the catalog listing the record belongs to.
		var brand = document.createElement('a')
		brand.href = state.home || '/'
		brand.textContent = state.brand || 'Nextcloud'
		brand.style.cssText = 'color:#fff;font-weight:600;text-decoration:none;'
		var sep = document.createElement('span')
		sep.textContent = '›'
		sep.style.cssText = 'opacity:.6;'
		var back = document.createElement('a')
		back.href = state.url
		back.textContent = state.label || 'Public data'
		back.style.cssText = 'color:#fff;opacity:.9;text-decoration:none;'
		bar.appendChild(brand)
		bar.appendChild(sep)
		bar.appendChild(back)
		// Third crumb: this record. Clicking navigates to the share's clean URL —
		// the breadcrumb "up": closes an open file, returns from a subfolder.
		if (state.share_name && state.share_url) {
			var sep2 = document.createElement('span')
			sep2.textContent = '›'
			sep2.style.cssText = 'opacity:.6;'
			var here = document.createElement('a')
			here.href = state.share_url
			here.textContent = state.share_name
			here.style.cssText = 'color:#fff;text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:40vw;'
			bar.appendChild(sep2)
			bar.appendChild(here)
		}

		// ── Right side: attribution + one-click add ──────────────────────────
		var right = document.createElement('div')
		right.style.cssText = 'margin-left:auto;display:flex;align-items:center;gap:12px;min-width:0;'
		bar.appendChild(right)

		// Attribution: "Shared by NAME · inst · date [ORCID iD]"
		if (state.owner_name) {
			var attr = document.createElement('span')
			attr.style.cssText = 'opacity:.85;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;'
			var when = ''
			try {
				if (state.stime) {
					when = ' · ' + new Date(state.stime * 1000).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
				}
			} catch (e) { /* date optional */ }
			attr.textContent = t('files_picocms', 'Shared by {name}', { name: state.owner_name })
				+ (state.institution ? ' · ' + state.institution : '') + when
			right.appendChild(attr)
			if (state.orcid) {
				var oa = document.createElement('a')
				oa.href = 'https://orcid.org/' + encodeURIComponent(state.orcid)
				oa.target = '_blank'
				oa.rel = 'noopener'
				oa.title = 'ORCID iD: ' + state.orcid
				oa.style.cssText = 'display:inline-flex;align-items:center;flex:0 0 auto;'
				oa.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" style="width:16px;height:16px"><circle cx="128" cy="128" r="128" fill="#A6CE39"/><path fill="#fff" d="M86.3 186.2H70.9V79.1h15.4v107.1zM108.9 79.1h41.6c39.6 0 57 28.3 57 53.6 0 27.5-21.5 53.6-56.8 53.6h-41.8V79.1zm15.4 93.3h24.5c34.9 0 42.9-26.5 42.9-39.7 0-21.5-13.7-39.7-43.7-39.7h-23.7v79.4zM88.7 56.8c0 5.5-4.5 10.1-10.1 10.1-5.6 0-10.1-4.6-10.1-10.1 0-5.6 4.5-10.1 10.1-10.1 5.6 0 10.1 4.6 10.1 10.1z"/></svg>'
				right.appendChild(oa)
			}
		}

		// One-click mount into the visitor's own files (login detour if needed).
		if (!state.is_owner && state.token) {
			var addBtn = document.createElement('button')
			addBtn.textContent = t('files_picocms', 'Add to my ScienceData')
			addBtn.style.cssText = 'flex:0 0 auto;font-size:12px;line-height:1;padding:6px 10px;border-radius:3px;border:1px solid rgba(255,255,255,.55);cursor:pointer;background:rgba(255,255,255,.12);color:#fff;font-weight:600;white-space:nowrap;'
			right.appendChild(addBtn)
			function doAdd() {
				addBtn.disabled = true
				addBtn.textContent = t('files_picocms', 'Adding…')
				fetch(OC.getRootPath() + '/ocs/v2.php/apps/files_sharding/api/v1/save-share?format=json', {
					method: 'POST',
					headers: {
						'OCS-APIREQUEST': 'true',
						'requesttoken': OC.requestToken,
						'Content-Type': 'application/json',
					},
					body: JSON.stringify({ token: state.token }),
				}).then(function (r) {
					if (r.status === 401) {
						// not logged in → login detour, then auto-add on return
						var back = location.origin + location.pathname + '?sd_autoadd=1'
						location.href = OC.getRootPath() + '/index.php/login?redirect_url=' + encodeURIComponent(back)
						return null
					}
					return r.json()
				}).then(function (res) {
					if (!res) { return }
					var st = res.ocs && res.ocs.data && res.ocs.data.status
					if (st === 'added' || st === 'exists') {
						addBtn.textContent = st === 'added'
							? t('files_picocms', 'Added ✓ — see "Shared with you"')
							: t('files_picocms', 'Already in your files ✓')
					} else if (st === 'own') {
						addBtn.textContent = t('files_picocms', 'This is your own share')
					} else {
						addBtn.disabled = false
						addBtn.textContent = t('files_picocms', 'Add to my ScienceData')
						OC.Notification && OC.Notification.showTemporary((res.ocs && res.ocs.data && res.ocs.data.message) || t('files_picocms', 'Adding failed'))
					}
				}).catch(function () {
					addBtn.disabled = false
					addBtn.textContent = t('files_picocms', 'Add to my ScienceData')
				})
			}
			addBtn.addEventListener('click', doAdd)
			// returning from the login detour → complete the add automatically
			if (/[?&]sd_autoadd=1/.test(location.search) && state.logged_in) {
				try { history.replaceState(null, '', location.pathname) } catch (e) { /* cosmetic */ }
				doAdd()
			}
		}

		// Stock OCM entry in the 3-dots menu ("Add to your Nextcloud" — for visitors
		// from OTHER services): relabel to make its scope explicit next to our
		// one-click. Targeted by its stable NcListItem anchor id, locale-independent.
		function relabelExternal(root) {
			try {
				var a = null
				if (root && root.id === 'save--link') { a = root }
				else if (root && root.querySelector) { a = root.querySelector('#save--link') }
				if (!a) { return }
				var nm = a.querySelector('.list-item-content__name') || a
				if (!nm.dataset.sdRelabeled) {
					nm.dataset.sdRelabeled = '1'
					nm.textContent = t('files_picocms', 'Add to external Nextcloud')
				}
			} catch (e) { /* cosmetic */ }
		}
		relabelExternal(document)
		try {
			new MutationObserver(function (muts) {
				muts.forEach(function (m) {
					m.addedNodes.forEach(function (n) { if (n.nodeType === 1) { relabelExternal(n) } })
				})
			}).observe(document.body, { childList: true, subtree: true })
		} catch (e) { /* cosmetic */ }

		document.body.appendChild(bar)
		// push the page down so the stock share header isn't covered
		document.body.style.marginTop = '40px'
	}
	// decodeURIComponent anchor: keeps minifiers from dropping the block.
	decodeURIComponent('')
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', insert)
	} else {
		insert()
	}
})()
