<?php

declare(strict_types=1);

namespace OCA\FilesPicoCMS\Controller;

use OCA\FilesPicoCMS\Service\SiteService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

class ApiController extends OCSController {
	public function __construct(
		string              $appName,
		IRequest            $request,
		private SiteService $siteService,
		private IUserSession $userSession,
		private LoggerInterface $logger,
		private IConfig     $config,
		private IRootFolder $rootFolder,
		private IShareManager $shareManager,
		private IURLGenerator $urlGenerator,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Opt a folder in/out of the public ScienceData catalog (the "Publish to
	 * catalog" file action). Ensures the folder has a public link share, then
	 * sets/clears the files_picocms:catalog_listed attribute on it. Listing does not
	 * change the share's permissions — it only marks an already-public folder as
	 * discoverable. Returns the public link so the UI can show/nudge.
	 */
	#[NoAdminRequired]
	public function setCatalogListed(int $fileId, bool $listed = true): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['status' => 'error', 'message' => 'not logged in'], Http::STATUS_UNAUTHORIZED);
		}
		$uid = $user->getUID();

		$nodes = $this->rootFolder->getUserFolder($uid)->getById($fileId);
		$node = $nodes[0] ?? null;
		if ($node === null) {
			return new DataResponse(['status' => 'error', 'message' => 'not found'], Http::STATUS_NOT_FOUND);
		}

		$shares = $this->shareManager->getSharesBy($uid, IShare::TYPE_LINK, $node, false, 50);
		$share = $shares[0] ?? null;

		if ($share === null) {
			if (!$listed) {
				// Nothing public and asked to unlist → nothing to do.
				return new DataResponse(['status' => 'ok', 'listed' => false]);
			}
			$share = $this->shareManager->newShare();
			$share->setNode($node)
				->setShareType(IShare::TYPE_LINK)
				->setSharedBy($uid)
				->setPermissions(Constants::PERMISSION_READ);
			$share = $this->shareManager->createShare($share);
		}

		$attrs = $share->getAttributes() ?? $share->newAttributes();
		$attrs->setAttribute('files_picocms', 'catalog_listed', $listed);
		$share->setAttributes($attrs);
		$this->shareManager->updateShare($share);

		return new DataResponse([
			'status' => 'ok',
			'listed' => $listed,
			'token'  => $share->getToken(),
			'url'    => $this->urlGenerator->linkToRouteAbsolute('files_sharing.sharecontroller.showShare', ['token' => $share->getToken()]),
		]);
	}

	/**
	 * Record that the current user has seen the welcome modal and accepted the
	 * terms (passive consent). Sets a per-user flag with the acceptance time, so
	 * WelcomeListener stops injecting the modal.
	 */
	#[NoAdminRequired]
	public function setWelcomed(): DataResponse {
		$user = $this->userSession->getUser();
		if ($user !== null) {
			$this->config->setUserValue($user->getUID(), 'files_picocms', 'welcomed', (string)time());
		}
		return new DataResponse(['status' => 'ok']);
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
