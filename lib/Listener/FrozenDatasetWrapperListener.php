<?php

declare(strict_types=1);

namespace OCA\FilesPicoCMS\Listener;

use OC\Files\Filesystem;
use OCA\FilesPicoCMS\Service\FrozenDatasets;
use OCA\FilesPicoCMS\Storage\FrozenDatasetWrapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\BeforeFileSystemSetupEvent;

/**
 * Wraps users' home storages in FrozenDatasetWrapper (published datasets are
 * read-only). Priority 0: outside the trash bin (1), so a delete is refused
 * before it becomes a move to the trash.
 *
 * @template-implements IEventListener<BeforeFileSystemSetupEvent>
 */
class FrozenDatasetWrapperListener implements IEventListener {
	public function __construct(
		private FrozenDatasets $frozenDatasets,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof BeforeFileSystemSetupEvent) {
			return;
		}
		$frozenDatasets = $this->frozenDatasets;
		Filesystem::addStorageWrapper(
			'files_picocms_frozen',
			static function (string $mountPoint, $storage) use ($frozenDatasets) {
				// home storages only: mount point /<uid>/
				$parts = array_values(array_filter(explode('/', $mountPoint)));
				if (count($parts) !== 1 || !$storage->instanceOfStorage(\OCP\Files\IHomeStorage::class)) {
					return $storage;
				}
				return new FrozenDatasetWrapper(['storage' => $storage, 'frozenDatasets' => $frozenDatasets]);
			},
			0
		);
	}
}
