<?php

declare(strict_types=1);

namespace OCA\FilesPicoCMS\Listener;

use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\Util;

/**
 * Catalog top bar on PUBLIC SHARE pages of catalog-listed shares (the
 * repository's "record pages"). Zenodo-style navigation (Frederik 2026-08-26):
 * a record opens in the SAME tab and the persistent top bar is the way back —
 * brand/name links to the catalog listing. Only injected when the rendered
 * share carries the files_picocms:catalog_listed attribute; ordinary share
 * pages are untouched.
 *
 * @implements IEventListener<BeforeTemplateRenderedEvent>
 */
class CatalogBannerListener implements IEventListener {
	public function __construct(
		private IInitialState $initialState,
		private IConfig       $config,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof BeforeTemplateRenderedEvent)) {
			return;
		}
		// ALL public share pages: drop the stock footer (theming slogan + link) —
		// clutter under the content; records carry branding in the top banner.
		Util::addStyle('files_picocms', 'public-share');
		// …and harden mis-rooted public-DAV requests (username instead of the
		// share token → 503; see js/public-share.js).
		Util::addScript('files_picocms', 'public-share');
		try {
			$attrs = $event->getShare()->getAttributes();
			if ($attrs === null || !$attrs->getAttribute('files_picocms', 'catalog_listed')) {
				return;
			}
		} catch (\Throwable) {
			return;
		}
		$sites = array_filter(array_map('trim',
			explode(',', (string)$this->config->getSystemValue('files_picocms.repository_sites', 'repository'))));
		$site = $sites !== [] ? reset($sites) : 'repository';
		// Record crumb: the share's presented name + its CLEAN page URL. Clicking
		// it is the breadcrumb "up": closes an open file / returns from a
		// subfolder (a plain navigation resets the public files view).
		$share = $event->getShare();
		$label = trim((string)$share->getLabel());
		if ($label === '') {
			try {
				$label = $share->getNode()->getName();
			} catch (\Throwable) {
				$label = '';
			}
		}
		$front = (string)$this->config->getSystemValue('files_picocms.frontpage_site', 'welcome');
		$this->initialState->provideInitialState('catalog_banner', [
			'url'        => '/remote.php/sites/' . rawurlencode($site) . '/',
			'home'       => '/remote.php/sites/' . rawurlencode($front) . '/',
			'brand'      => (string)$this->config->getSystemValue('files_picocms.brand_name', 'Nextcloud'),
			'label'      => (string)$this->config->getSystemValue('files_picocms.catalog_label', 'Public data'),
			'share_name' => $label,
			'share_url'  => '/index.php/s/' . rawurlencode($share->getToken()),
		]);
		Util::addScript('files_picocms', 'catalog-banner');
	}
}
