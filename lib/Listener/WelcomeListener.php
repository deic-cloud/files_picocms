<?php

declare(strict_types=1);

namespace OCA\FilesPicoCMS\Listener;

use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\IUserSession;
use OCP\Util;

/**
 * One-time welcome + terms-consent modal.
 *
 * On any logged-in page render, if the user hasn't yet acknowledged (per-user
 * flag files_picocms:welcomed), inject a small vanilla-JS modal. Its "Continue"
 * button records consent via OCS api#setWelcomed, after which this listener stops
 * injecting the script. Replaces the disabled stock firstrunwizard with our own
 * honest welcome, and captures passive terms consent ("by continuing you agree").
 *
 * @template-implements IEventListener<BeforeTemplateRenderedEvent>
 */
class WelcomeListener implements IEventListener {
	public function __construct(
		private IUserSession $userSession,
		private IConfig $config,
		private IInitialState $initialState,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof BeforeTemplateRenderedEvent) || !$event->isLoggedIn()) {
			return;
		}
		$user = $this->userSession->getUser();
		if ($user === null) {
			return;
		}
		if ($this->config->getUserValue($user->getUID(), 'files_picocms', 'welcomed', '') !== '') {
			return;
		}
		// Brand identity from config (keeps the app generic); read by welcome.js.
		$brand = trim((string)$this->config->getSystemValue('files_picocms.brand_name', 'Nextcloud'));
		$this->initialState->provideInitialState('welcome', [
			'brand' => $brand !== '' ? $brand : 'Nextcloud',
			'blurb' => (string)$this->config->getSystemValue('files_picocms.brand_blurb', ''),
		]);
		Util::addScript('files_picocms', 'welcome');
		Util::addStyle('files_picocms', 'welcome');
	}
}
