<?php

declare(strict_types=1);

namespace OCA\FilesPicoCMS\Controller;

use OCA\FilesPicoCMS\Service\SiteService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Util;

class PageController extends Controller {
	public function __construct(
		string               $appName,
		IRequest             $request,
		private SiteService  $siteService,
		private IUserSession $userSession,
		private IConfig      $config,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		$uid         = $this->userSession->getUser()?->getUID() ?? '';
		$sites       = $this->siteService->listSites($uid);
		$email       = $this->config->getUserValue($uid, 'settings', 'email', '');
		$servePublic = $this->siteService->getServePublicUrl($uid);

		Util::addScript('files_picocms', 'app');
		Util::addStyle('files_picocms', 'app');

		return new TemplateResponse('files_picocms', 'index', [
			'sites'        => $sites,
			'email'        => $email,
			'serve_public' => $servePublic,
		]);
	}
}
