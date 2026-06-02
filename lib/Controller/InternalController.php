<?php

declare(strict_types=1);

namespace OCA\FilesPicoCMS\Controller;

use OCA\FilesPicoCMS\Service\SiteService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;

/**
 * Inter-server endpoints called by files_sharding's InterServerClient
 * from silos that cannot perform DB writes locally.
 *
 * Authentication: Bearer token matching files_sharding_shared_secret in config.php.
 * All operations are forwarded to the master via InterServerClient::postDirect/getDirect
 * at /index.php/apps/files_picocms/internal/{action}.
 */
class InternalController extends Controller {
	public function __construct(
		string              $appName,
		IRequest            $request,
		private SiteService $siteService,
		private IConfig     $config,
	) {
		parent::__construct($appName, $request);
	}

	private function checkSecret(): bool {
		$secret = (string)$this->config->getSystemValue('files_sharding_shared_secret', '');
		if ($secret === '') {
			return false;
		}
		$auth = $this->request->getHeader('Authorization');
		return $auth === 'Bearer ' . $secret;
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function listSites(string $uid): JSONResponse {
		if (!$this->checkSecret()) {
			return new JSONResponse(['error' => 'Unauthorized'], 401);
		}
		return new JSONResponse($this->siteService->listSites($uid));
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function addSite(string $uid, string $folder, string $name, string $group = '', string $rename = 'no'): JSONResponse {
		if (!$this->checkSecret()) {
			return new JSONResponse(['error' => 'Unauthorized'], 401);
		}
		$ok = $this->siteService->addSite($uid, $folder, $name, $group, $rename === 'yes');
		return new JSONResponse($ok ? ['msg' => 'Added'] : ['error' => 'Name taken'], $ok ? 200 : 400);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function removeSite(string $uid, string $folder): JSONResponse {
		if (!$this->checkSecret()) {
			return new JSONResponse(['error' => 'Unauthorized'], 401);
		}
		$ok = $this->siteService->removeSite($uid, $folder);
		return new JSONResponse($ok ? ['msg' => 'Removed'] : ['error' => 'Not found'], $ok ? 200 : 404);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function lookupSite(string $site): JSONResponse {
		if (!$this->checkSecret()) {
			return new JSONResponse(['error' => 'Unauthorized'], 401);
		}
		$info = $this->siteService->lookupSite($site);
		return new JSONResponse($info ?? []);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function getSampleFolder(): JSONResponse {
		if (!$this->checkSecret()) {
			return new JSONResponse(['error' => 'Unauthorized'], 401);
		}
		return new JSONResponse($this->siteService->getSampleFolder());
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function setSampleFolder(string $owner, string $path): JSONResponse {
		if (!$this->checkSecret()) {
			return new JSONResponse(['error' => 'Unauthorized'], 401);
		}
		$this->siteService->setSampleFolder($owner, $path);
		return new JSONResponse(['msg' => 'Saved']);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function getServePublic(string $uid): JSONResponse {
		if (!$this->checkSecret()) {
			return new JSONResponse(['error' => 'Unauthorized'], 401);
		}
		return new JSONResponse(['serve' => $this->siteService->getServePublicUrl($uid) ? 'yes' : 'no']);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function setServePublic(string $uid, string $serve): JSONResponse {
		if (!$this->checkSecret()) {
			return new JSONResponse(['error' => 'Unauthorized'], 401);
		}
		$this->siteService->setServePublicUrl($uid, $serve === 'yes');
		return new JSONResponse(['serve' => $serve]);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function getUserId(string $email): JSONResponse {
		if (!$this->checkSecret()) {
			return new JSONResponse(['error' => 'Unauthorized'], 401);
		}
		$uid = $this->siteService->getUserIdFromEmail($email);
		return new JSONResponse(['uid' => $uid]);
	}
}
