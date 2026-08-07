<?php

declare(strict_types=1);

namespace OCA\FilesPicoCMS\Listener;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/** @template-implements IEventListener<LoadAdditionalScriptsEvent> */
class LoadSidebarScriptsListener implements IEventListener {
	public function handle(Event $event): void {
		if (!($event instanceof LoadAdditionalScriptsEvent)) {
			return;
		}
		Util::addScript('files_picocms', 'sidebar');
		Util::addStyle('files_picocms', 'sidebar');
		// "Publish to catalog" folder action (bundled; registers via @nextcloud/files).
		Util::addScript('files_picocms', 'files-action', 'files');
	}
}
