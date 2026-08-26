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
		bar.style.cssText = 'position:fixed;top:0;left:0;right:0;height:36px;z-index:9999;'
			+ 'display:flex;align-items:center;gap:14px;padding:0 16px;'
			+ 'background:#1b456d;color:#fff;font-size:13px;'
		var brand = document.createElement('a')
		brand.href = state.url
		brand.textContent = state.brand || 'Nextcloud'
		brand.style.cssText = 'color:#fff;font-weight:600;text-decoration:none;'
		var back = document.createElement('a')
		back.href = state.url
		back.textContent = '‹ ' + (state.label || 'Public data')
		back.style.cssText = 'color:#fff;opacity:.85;text-decoration:none;'
		bar.appendChild(brand)
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
