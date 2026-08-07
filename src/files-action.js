/**
 * "List in ScienceData catalog" folder action.
 *
 * Bundled because @nextcloud/files' registerFileAction is not exposed to plain
 * JS. Flow: confirm → POST the folder's fileId to OCS api#setCatalogListed
 * (which ensures a public link share + sets sciencedata:catalog_listed) → mark
 * the node shared in the file list (like the standard share flow) → show a
 * confirmation that nudges toward a citable DOI via the "Publish…" action.
 *
 * Only @nextcloud/files, @nextcloud/l10n and @nextcloud/event-bus are imported
 * (framework-agnostic); dialogs/toasts are hand-rolled to keep the bundle off
 * @nextcloud/dialogs (v6 pulls Vue 3 and clashes with the reused Vue 2 modules).
 */
import { registerFileAction } from '@nextcloud/files'
import { translate as t } from '@nextcloud/l10n'
import { emit } from '@nextcloud/event-bus'

const OCS = (window.OC?.webroot || '') + '/ocs/v2.php/apps/files_picocms/api/v1'
const SHARE_TYPE_LINK = 3 // OCP\Share\IShare::TYPE_LINK

// Material "public" (globe) icon.
const ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">'
	+ '<path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93'
	+ 'c-3.94-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1'
	+ 'v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>'

function post(path, params) {
	return fetch(OCS + path + '?format=json', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'OCS-APIREQUEST': 'true',
			requesttoken: window.OC.requestToken,
		},
		body: params,
	}).then((r) => r.json())
}

let stylesInjected = false
function injectStyles() {
	if (stylesInjected) { return }
	stylesInjected = true
	const s = document.createElement('style')
	s.textContent = `
	.sd-cat-overlay{position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;padding:1rem}
	.sd-cat-card{background:var(--color-main-background,#fff);color:var(--color-main-text,#1a2230);max-width:460px;width:100%;border-radius:12px;padding:1.75rem;box-shadow:0 10px 40px rgba(0,0,0,.3);line-height:1.5}
	.sd-cat-card h2{margin:0 0 .75rem;font-size:1.3rem}
	.sd-cat-card p{margin:0 0 .9rem}
	.sd-cat-link{display:block;word-break:break-all;background:var(--color-background-hover,#f4f4f2);border-radius:6px;padding:.5rem .7rem;font-size:.9rem;margin-bottom:.9rem}
	.sd-cat-nudge{font-size:.92rem;color:var(--color-text-maxcontrast,#5b6672)}
	.sd-cat-actions{display:flex;gap:.5rem;justify-content:flex-end;margin-top:.5rem}
	.sd-cat-btn{border:none;border-radius:8px;padding:.55rem 1.2rem;font-weight:600;cursor:pointer;background:var(--color-primary-element,#235789);color:var(--color-primary-element-text,#fff)}
	.sd-cat-btn.secondary{background:var(--color-background-dark,#ececec);color:var(--color-main-text,#1a2230)}
	.sd-cat-toast{position:fixed;bottom:1.25rem;left:50%;transform:translateX(-50%);z-index:100001;background:#8a0000;color:#fff;padding:.7rem 1.1rem;border-radius:8px;box-shadow:0 6px 24px rgba(0,0,0,.3);max-width:90vw;font-size:.95rem}`
	document.head.appendChild(s)
}

function toastError(msg) {
	injectStyles()
	const el = document.createElement('div')
	el.className = 'sd-cat-toast'
	el.textContent = msg
	document.body.appendChild(el)
	setTimeout(() => el.remove(), 5000)
}

function modal(innerHTML) {
	injectStyles()
	const overlay = document.createElement('div')
	overlay.className = 'sd-cat-overlay'
	overlay.innerHTML = '<div class="sd-cat-card" role="dialog" aria-modal="true">' + innerHTML + '</div>'
	document.body.appendChild(overlay)
	return overlay
}

function confirmPublish(name) {
	return new Promise((resolve) => {
		const overlay = modal(
			'<h2>' + t('files_picocms', 'List in the ScienceData catalog?') + '</h2>'
			+ '<p>' + t('files_picocms', '“{name}” will be shared with a public link — anyone with the link can view it — and listed in the public ScienceData catalog.').replace('{name}', name) + '</p>'
			+ '<div class="sd-cat-actions">'
			+ '<button type="button" class="sd-cat-btn secondary" data-act="cancel">' + t('files_picocms', 'Cancel') + '</button>'
			+ '<button type="button" class="sd-cat-btn" data-act="ok">' + t('files_picocms', 'List it') + '</button>'
			+ '</div>')
		const done = (val) => { overlay.remove(); resolve(val) }
		overlay.querySelector('[data-act="ok"]').addEventListener('click', () => done(true))
		overlay.querySelector('[data-act="cancel"]').addEventListener('click', () => done(false))
		overlay.addEventListener('click', (e) => { if (e.target === overlay) { done(false) } })
	})
}

function nudge(url) {
	const overlay = modal(
		'<h2>' + t('files_picocms', 'Listed in the public catalog') + '</h2>'
		+ '<p>' + t('files_picocms', 'Anyone with the link can now view this folder, and it appears in the public ScienceData catalog.') + '</p>'
		+ '<a class="sd-cat-link" href="' + url + '" target="_blank" rel="noopener">' + url + '</a>'
		+ '<p class="sd-cat-nudge">' + t('files_picocms', 'Want a citable DOI for this dataset? Use the “Publish…” action to mint one via Zenodo or Figshare.') + '</p>'
		+ '<div class="sd-cat-actions"><button type="button" class="sd-cat-btn" data-act="ok">' + t('files_picocms', 'Done') + '</button></div>')
	const close = () => overlay.remove()
	overlay.querySelector('[data-act="ok"]').addEventListener('click', close)
	overlay.addEventListener('click', (e) => { if (e.target === overlay) { close() } })
}

// Reflect the new public-link share in the file list immediately, like the
// standard share flow: add TYPE_LINK to the node's share-types and re-render.
function markShared(node) {
	try {
		const cur = Object.values(node.attributes?.['share-types'] || {}).flat()
		if (!cur.includes(SHARE_TYPE_LINK)) {
			node.attributes = node.attributes || {}
			node.attributes['share-types'] = [...cur, SHARE_TYPE_LINK]
		}
		emit('files:node:updated', node)
	} catch (e) {
		// Non-fatal: the indicator will appear on next reload.
		console.warn('[files_picocms] could not refresh share indicator', e)
	}
}

async function publish(node) {
	const res = await post('/catalog', 'fileId=' + encodeURIComponent(node.fileid) + '&listed=1')
	if (res && res.ocs && res.ocs.meta && res.ocs.meta.status === 'ok') {
		markShared(node)
		nudge(res.ocs.data.url)
	} else {
		toastError(t('files_picocms', 'Could not list in catalog') + (res?.ocs?.meta?.message ? ': ' + res.ocs.meta.message : ''))
	}
}

registerFileAction({
	id: 'files_picocms-catalog',
	displayName: () => t('files_picocms', 'List in ScienceData catalog'),
	title: () => t('files_picocms', 'Share this folder publicly and list it in the ScienceData catalog'),
	iconSvgInline: () => ICON,
	enabled: ({ nodes }) => Array.isArray(nodes) && nodes.length === 1 && nodes[0].type === 'folder',
	exec: async ({ nodes }) => {
		const node = nodes[0]
		try {
			if (await confirmPublish(node.basename || node.displayname || t('files_picocms', 'This folder'))) {
				await publish(node)
			}
		} catch (e) {
			toastError(t('files_picocms', 'Listing failed') + ': ' + (e && e.message ? e.message : e))
			console.error('[files_picocms]', e)
		}
		return null
	},
	order: 26,
})
