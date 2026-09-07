<?php

declare(strict_types=1);

namespace OCA\FilesPicoCMS\Listener;

use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\IRequest;
use OCP\Util;

/**
 * Deployment-branded /settings/help page — CONFIG-GATED, no core change.
 *
 * When the system value `files_picocms.help_docs_url` names the deployment's
 * own documentation, the stock Nextcloud help page gets a small script that
 * (client-side) retitles the page "Documentation", puts a "<brand>
 * documentation" button first, keeps the Nextcloud user documentation
 * (relabelled "Nextcloud documentation"), and removes the admin-docs /
 * general-docs / forum buttons — which point at nextcloud.com resources that
 * mostly confuse end users of a branded service. Unset (the default), the
 * stock page is untouched — app-store installs see no difference.
 *
 * @implements IEventListener<BeforeTemplateRenderedEvent>
 */
class HelpPageScriptListener implements IEventListener {
	public function __construct(
		private IConfig       $config,
		private IRequest      $request,
		private IInitialState $initialState,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof BeforeTemplateRenderedEvent) || !$event->isLoggedIn()) {
			return;
		}
		$docsUrl = trim((string)$this->config->getSystemValue('files_picocms.help_docs_url', ''));
		if ($docsUrl === '') {
			return;
		}
		if (!str_contains($this->request->getPathInfo() ?: '', '/settings/help')) {
			return;
		}
		$brand = trim((string)$this->config->getSystemValue('files_picocms.brand_name', 'Nextcloud'));
		$this->initialState->provideInitialState('helpDocs', [
			'url'   => $docsUrl,
			'brand' => $brand !== '' ? $brand : 'Nextcloud',
		]);
		Util::addScript('files_picocms', 'help-page');
	}
}
