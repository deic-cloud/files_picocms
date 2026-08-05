<?php

declare(strict_types=1);

namespace OCA\FilesPicoCMS\AppInfo;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\FilesPicoCMS\Listener\LoadSidebarScriptsListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	public const APP_ID = 'files_picocms';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadSidebarScriptsListener::class);
		// Admin-overview warning if pretty URLs (/sites, /users) aren't rewritten to
		// the app. Guarded: the silo stub IRegistrationContext lacks this method.
		try {
			$context->registerSetupCheck(\OCA\FilesPicoCMS\SetupCheck\PrettyUrlsCheck::class);
		} catch (\Throwable) {
		}
	}

	public function boot(IBootContext $context): void {
	}
}
