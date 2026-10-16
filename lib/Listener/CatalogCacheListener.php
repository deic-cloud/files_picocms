<?php

declare(strict_types=1);

namespace OCA\FilesPicoCMS\Listener;

use OCA\FilesPicoCMS\Service\CatalogService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Share\Events\ShareCreatedEvent;
use OCP\Share\Events\ShareDeletedEvent;
use OCP\Share\IShare;
use OCP\SystemTag\ManagerEvent;
use OCP\SystemTag\MapperEvent;

/**
 * Drops the cached public catalogue (and landing-page records) as soon as
 * something that feeds it changes: a public link comes or goes, a tag is
 * assigned, unassigned, renamed or deleted, or a metadata value is written
 * (meta_data's FileMetadataChangedEvent, when that app is present). Without
 * this, an owner tagging a listed folder saw the old chips for minutes and
 * concluded the feature did not work.
 *
 * @template-implements IEventListener<Event>
 */
class CatalogCacheListener implements IEventListener {
	public function __construct(
		private CatalogService $catalog,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof ShareCreatedEvent || $event instanceof ShareDeletedEvent) {
			if ($event->getShare()->getShareType() !== IShare::TYPE_LINK) {
				return;
			}
		} elseif ($event instanceof MapperEvent) {
			if ($event->getObjectType() !== 'files') {
				return;
			}
		} elseif (!($event instanceof ManagerEvent)
			&& get_class($event) !== 'OCA\\MetaData\\Event\\FileMetadataChangedEvent') {
			return;
		}
		$this->catalog->invalidateCache();
	}
}
