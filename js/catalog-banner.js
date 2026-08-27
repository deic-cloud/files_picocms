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
		bar.style.cssText = 'position:fixed;top:0;left:0;right:0;height:36px;z-index:1500;'
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
		document.body.appendChild(bar)
		// push the page down so the stock share header isn't covered
		document.body.style.marginTop = '36px'
	}
	// decodeURIComponent anchor: keeps minifiers from dropping the block.
	decodeURIComponent('')
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', insert)
	} else {
		insert()
	}
})()
