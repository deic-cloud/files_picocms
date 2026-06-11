<?php

declare(strict_types=1);

namespace OCA\FilesPicoCMS\Controller;

use OCA\FilesPicoCMS\Service\SiteService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class ApiController extends OCSController {
	public function __construct(
		string              $appName,
		IRequest            $request,
		private SiteService $siteService,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function listSites(): DataResponse {
		$uid   = $this->userSession->getUser()?->getUID() ?? '';
		$sites = $this->siteService->listSites($uid);
		return new DataResponse($sites);
	}

	#[NoAdminRequired]
	public function addSite(string $folder, string $name, string $group = '', string $rename = 'no'): DataResponse {
		$uid    = $this->userSession->getUser()?->getUID() ?? '';
		$doRename = $rename === 'yes';
		$ok     = $this->siteService->addSite($uid, $folder, $name, $group, $doRename);
		if (!$ok) {
			return new DataResponse(['error' => 'Name taken: ' . $name], 400);
		}
		return new DataResponse(['msg' => 'Added']);
	}

	#[NoAdminRequired]
	public function removeSite(string $folder): DataResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		$ok  = $this->siteService->removeSite($uid, $folder);
		if (!$ok) {
			return new DataResponse(['error' => 'Not found'], 404);
		}
		return new DataResponse(['msg' => 'Removed']);
	}

	#[NoAdminRequired]
	public function createSite(
		string  $folder,
		string  $name       = '',
		string  $content    = '',
		string  $destination = '',
		string  $theme      = '',
		string  $copyThemes = 'no',
	): DataResponse {
		$uid  = $this->userSession->getUser()?->getUID() ?? '';
		$parts = pathinfo($folder);
		$siteName = $name !== '' ? $name : $parts['basename'];

		$res = $this->siteService->createPersonalSite(
			$uid,
			$folder,
			$siteName,
			$content    !== '' ? $content    : null,
			$destination !== '' ? $destination : null,
			$theme      !== '' ? $theme      : null,
			$copyThemes === 'yes',
		);

		return match ($res) {
			SiteService::OK                  => new DataResponse(['site' => $siteName]),
			SiteService::SITE_NAME_EXISTS    => new DataResponse(['error' => 'Site name taken: ' . $siteName], 400),
			SiteService::FOLDER_NOT_EMPTY    => new DataResponse(['error' => 'The folder ' . $folder . ' already exists and is not empty — choose another folder or rename the existing one.'], 400),
			SiteService::COPY_CONTENT_FAILED => new DataResponse(['error' => 'Failed to copy content'], 500),
			default                          => new DataResponse(['error' => 'Unexpected error'], 500),
		};
	}

	#[NoAdminRequired]
	public function getHelp(): DataResponse {
		// Return the wizard template data; rendered client-side
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		return new DataResponse([
			'team_folder'    => '/team',
			'blog_folder'    => '/blog',
			'doc_folder'     => '/documentation',
			'public_folder'  => '/public',
			'default_folder' => '/website',
		]);
	}

	#[NoAdminRequired]
	public function getServePublic(): DataResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		return new DataResponse(['serve' => $this->siteService->getServePublicUrl($uid) ? 'yes' : 'no']);
	}

	#[NoAdminRequired]
	public function setServePublic(string $serve): DataResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		$this->siteService->setServePublicUrl($uid, $serve === 'yes');
		return new DataResponse(['serve' => $serve]);
	}

	#[NoAdminRequired]
	public function getConfig(string $folder): DataResponse {
		$uid    = $this->userSession->getUser()?->getUID() ?? '';
		$result = $this->siteService->getSiteConfig($uid, $folder);
		return new DataResponse(['content' => $result['content'], 'file' => $result['file']]);
	}

	#[NoAdminRequired]
	public function putConfig(string $folder, string $content): DataResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		$ok  = $this->siteService->putSiteConfig($uid, $folder, $content);
		if (!$ok) {
			return new DataResponse(['error' => 'Save failed'], 500);
		}
		return new DataResponse(['msg' => 'Saved']);
	}

	// Admin-only endpoints (no #[NoAdminRequired])

	public function getSampleFolder(): DataResponse {
		return new DataResponse($this->siteService->getSampleFolder());
	}

	public function setSampleFolder(string $owner, string $path): DataResponse {
		$this->siteService->setSampleFolder($owner, $path);
		return new DataResponse(['msg' => 'Saved']);
	}
}
