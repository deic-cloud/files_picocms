<?php

declare(strict_types=1);

namespace OCA\FilesPicoCMS\Settings;

use OCA\FilesPicoCMS\Service\SiteService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IUserSession;
use OCP\Settings\ISettings;
use OCP\Util;

class PersonalSettings implements ISettings {
	public function __construct(
		private SiteService  $siteService,
		private IUserSession $userSession,
		private IConfig      $config,
	) {
	}

	public function getForm(): TemplateResponse {
		$uid         = $this->userSession->getUser()?->getUID() ?? '';
		$sites       = $this->siteService->listSites($uid);
		$servePublic = $this->siteService->getServePublicUrl($uid);
		$email       = $this->config->getUserValue($uid, 'settings', 'email', '');

		Util::addScript('files_picocms', 'personalsettings');
		Util::addStyle('files_picocms', 'personalsettings');

		return new TemplateResponse('files_picocms', 'personalsettings', [
			'site_folders'     => $sites,
			'serve_public_url' => $servePublic,
			'email'            => $email,
			'url_prefix'       => $this->siteService->urlPrefix(),
		]);
	}

	public function getSection(): string {
		return 'personal-info';
	}

	public function getPriority(): int {
		return 50;
	}
}
