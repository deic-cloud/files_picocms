<?php

declare(strict_types=1);

namespace OCA\FilesPicoCMS\Listener;

use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\IRequest;
use OCP\Util;

/**
 * Hide the stock "development notice" at the bottom of Settings → Personal
 * info ("Reasons to use Nextcloud in your organization", social buttons) —
 * CONFIG-GATED, no core change.
 *
 * The block is registered unconditionally by the settings app
 * (ServerDevNotice) and cannot be unregistered, so this adds one stylesheet on
 * personal-settings pages that hides `.section.development-notice`. Only when
 * the system value `files_picocms.hide_dev_notice` is true; unset (the
 * default), the stock page is untouched — app-store installs see no difference.
 *
 * @implements IEventListener<BeforeTemplateRenderedEvent>
 */
class DevNoticeHideListener implements IEventListener {
	public function __construct(
		private IConfig  $config,
		private IRequest $request,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof BeforeTemplateRenderedEvent) || !$event->isLoggedIn()) {
			return;
		}
		if (!$this->config->getSystemValueBool('files_picocms.hide_dev_notice', false)) {
			return;
		}
		if (!str_contains($this->request->getPathInfo() ?: '', '/settings/user')) {
			return;
		}
		Util::addStyle('files_picocms', 'hide-dev-notice');
	}
}
