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
		// 2026-08-25: the "Share as public dataset" file action is retired — it was
		// a second, write-only path to a public link (confusing; no state feedback).
		// Catalog listing is now a checkbox in files_sharding's public-link popup
		// (same /api/v1/catalog endpoint). Source kept: src/files-action.js.
		// Util::addScript('files_picocms', 'files-action', 'files');
	}
}
