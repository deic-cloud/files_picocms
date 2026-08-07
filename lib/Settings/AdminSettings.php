<?php

declare(strict_types=1);

namespace OCA\FilesPicoCMS\Settings;

use OCA\FilesPicoCMS\Service\SiteService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCP\Util;

class AdminSettings implements ISettings {
	public function __construct(
		private SiteService $siteService,
		private IConfig $config,
	) {
	}

	public function getForm(): TemplateResponse {
		$sample = $this->siteService->getSampleFolder();
		Util::addScript('files_picocms', 'settings');
		Util::addStyle('files_picocms', 'settings');
		return new TemplateResponse('files_picocms', 'settings', [
			'samplesiteowner' => $sample['owner'],
			'samplesitepath'  => $sample['path'],
			'contentuser'     => $this->config->getAppValue('files_picocms', 'content_user', 'cloud'),
		]);
	}

	public function getSection(): string {
		return 'additional';
	}

	public function getPriority(): int {
		return 50;
	}
}
