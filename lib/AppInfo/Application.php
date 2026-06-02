<?php

declare(strict_types=1);

namespace OCA\FilesPicoCMS\AppInfo;

use OCA\FilesPicoCMS\Settings\AdminSettings;
use OCA\FilesPicoCMS\Settings\PersonalSettings;
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
		$context->registerSettings(PersonalSettings::class);
		$context->registerSettings(AdminSettings::class);
	}

	public function boot(IBootContext $context): void {
	}
}
