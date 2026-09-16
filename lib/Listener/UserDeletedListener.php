<?php

declare(strict_types=1);

namespace OCA\FilesPicoCMS\Listener;

use OCA\FilesPicoCMS\Service\SiteService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;

/**
 * A deleted account's site registrations go with it — otherwise the site
 * names stay taken forever ("Site name taken") and the master registry keeps
 * pointing at a home that no longer exists. removeSite() also forwards the
 * deletion to the master registry.
 *
 * @implements IEventListener<UserDeletedEvent>
 */
class UserDeletedListener implements IEventListener {
	public function __construct(
		private SiteService     $siteService,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof UserDeletedEvent)) {
			return;
		}
		$uid = $event->getUser()->getUID();
		try {
			foreach ($this->siteService->listSites($uid) as $site) {
				$this->siteService->removeSite($uid, (string)$site['path']);
			}
		} catch (\Throwable $e) {
			$this->logger->warning('files_picocms: could not remove the sites of deleted user ' . $uid . ': ' . $e->getMessage());
		}
	}
}
