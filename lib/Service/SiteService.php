<?php

declare(strict_types=1);

namespace OCA\FilesPicoCMS\Service;

use OCA\FilesPicoCMS\Db\Site;
use OCA\FilesPicoCMS\Db\SiteMapper;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class SiteService {
	public const OK                 = 0;
	public const SITE_NAME_EXISTS   = 1;
	public const COPY_CONTENT_FAILED = 2;
	public const FOLDER_NOT_EMPTY   = 3;

	public function __construct(
		private SiteMapper    $mapper,
		private IConfig       $config,
		private IRootFolder   $rootFolder,
		private IUserManager  $userManager,
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	/** @return array[] rows with uid/site/path/gid */
	public function listSites(string $uid): array {
		$sites = $this->mapper->findByUid($uid);
		return array_map(fn(Site $s) => [
			'uid'  => $s->getUid(),
			'site' => $s->getSite(),
			'path' => $s->getPath(),
			'gid'  => $s->getGid(),
		], $sites);
	}

	public function siteExists(string $name): bool {
		return $this->mapper->findByName($name) !== null;
	}

	/** @return array|null  row with uid/site/path/gid or null */
	public function lookupSite(string $name): ?array {
		$site = $this->mapper->findByName($name);
		if ($site === null) {
			return null;
		}
		return [
			'uid'  => $site->getUid(),
			'site' => $site->getSite(),
			'path' => $site->getPath(),
			'gid'  => $site->getGid(),
		];
	}

	public function addSite(string $uid, string $path, string $name, string $gid = '', bool $rename = false): bool {
		if (!$rename && $this->siteExists($name)) {
			return false;
		}
		// Master is the source of truth for site names — a name taken by a
		// user on another silo must be rejected here too.
		$remote = $this->masterLookup($name);
		if ($remote !== null && !($remote['uid'] === $uid && $remote['path'] === $path)) {
			return false;
		}
		if ($rename) {
			$existing = $this->mapper->findByUidAndPath($uid, $path);
			if ($existing === null) {
				return false;
			}
			$existing->setSite($name);
			$this->mapper->update($existing);
			$this->syncToMaster('internal/sites', [
				'uid' => $uid, 'folder' => $path, 'name' => $name, 'group' => $gid, 'rename' => 'yes',
			]);
			return true;
		}
		$site = new Site();
		$site->setUid($uid);
		$site->setSite($name);
		$site->setPath($path);
		$site->setGid($gid);
		$this->mapper->insert($site);
		$this->syncToMaster('internal/sites', [
			'uid' => $uid, 'folder' => $path, 'name' => $name, 'group' => $gid,
		]);
		return true;
	}

	public function removeSite(string $uid, string $path): bool {
		$removed = $this->mapper->deleteByUidAndPath($uid, $path) > 0;
		if ($removed) {
			$this->syncToMaster('internal/sites/delete', ['uid' => $uid, 'folder' => $path]);
		}
		return $removed;
	}

	// ── Master registry sync ──────────────────────────────────────────────────
	// The master holds the authoritative copy of the site registry so it can
	// redirect /sites/{name} URLs to the hosting silo. Silos keep a local copy
	// for serving and forward every mutation to the master. All calls are
	// guarded: without files_sharding (or on the master itself) they no-op,
	// keeping the app independently installable.

	/** @return array{0: object, 1: string}|null  [InterServerClient, master base URL] */
	private function masterClient(): ?array {
		// Master reads the registry locally; only a silo talks to the master. Use the
		// app's URL-aware detection (Application::isMasterNode) — the old flag-only
		// check mis-detected the URL-auto-detected master (files_sharding_master unset,
		// master_url == overwrite.cli.url) as a SILO, so it HTTP-called ITSELF for
		// every site lookup — a blocking mod_php self-call on the front-page render
		// path that starves workers and times out (10s).
		if (\OCA\FilesPicoCMS\AppInfo\Application::isMasterNode($this->config)) {
			return null;
		}
		if (!class_exists(\OCA\FilesSharding\Service\InterServerClient::class)) {
			return null;
		}
		$url = rtrim((string)$this->config->getSystemValue('files_sharding_master_internal_url', ''), '/');
		if ($url === '') {
			$url = rtrim((string)$this->config->getSystemValue('files_sharding_master_url', ''), '/');
		}
		if ($url === '') {
			return null;
		}
		try {
			return [\OCP\Server::get(\OCA\FilesSharding\Service\InterServerClient::class), $url];
		} catch (\Throwable) {
			return null;
		}
	}

	/** Look up a site name in the master registry. Null = not found / not applicable. */
	private function masterLookup(string $name): ?array {
		$mc = $this->masterClient();
		if ($mc === null) {
			return null;
		}
		[$client, $url] = $mc;
		$data = $client->getDirect($url, 'internal/lookup', ['site' => $name], 'files_picocms');
		return (is_array($data) && !empty($data['site'])) ? $data : null;
	}

	/** Forward a registry mutation to the master. Failures are logged, not fatal. */
	private function syncToMaster(string $action, array $params): void {
		$mc = $this->masterClient();
		if ($mc === null) {
			return;
		}
		[$client, $url] = $mc;
		$result = $client->postDirect($url, $action, $params, 'files_picocms');
		if ($result === null) {
			$this->logger->error(
				'files_picocms: failed to sync site registry to master (' . $action . ' '
				. json_encode($params) . ') — master registry is now stale'
			);
		}
	}

	public function getSampleFolder(): array {
		return [
			'owner' => $this->config->getAppValue('files_picocms', 'samplesiteowner', ''),
			'path'  => $this->config->getAppValue('files_picocms', 'samplesitepath',  ''),
		];
	}

	public function setSampleFolder(string $owner, string $path): void {
		$this->config->setAppValue('files_picocms', 'samplesiteowner', $owner);
		$this->config->setAppValue('files_picocms', 'samplesitepath',  $path);
	}

	public function getServePublicUrl(string $uid): bool {
		return $this->config->getUserValue($uid, 'files_picocms', 'servepublicurl', 'no') === 'yes';
	}

	public function setServePublicUrl(string $uid, bool $serve): void {
		$this->config->setUserValue($uid, 'files_picocms', 'servepublicurl', $serve ? 'yes' : 'no');
		try {
			$this->syncWebsiteProfileField($uid, $serve);
		} catch (\Throwable $e) {
			$this->logger->warning('files_picocms: could not sync profile Website field for ' . $uid . ': ' . $e->getMessage());
		}
	}

	/**
	 * URL path prefix for site/user page URLs. Defaults to '/remote.php' — i.e.
	 * links use the /remote.php/sites and /remote.php/users remote services, which
	 * work on any web server with NO rewrite. Pretty URLs (/sites, /users) are
	 * opt-in: set 'files_picocms.url_prefix' => '' AND add a web-server rewrite.
	 */
	public function urlPrefix(): string {
		return rtrim((string)$this->config->getSystemValue('files_picocms.url_prefix', '/remote.php'), '/');
	}

	/**
	 * Canonical base URL for published links: the master (stable across user
	 * moves — it redirects to the hosting silo), falling back to this
	 * instance's own base URL on standalone installs.
	 */
	public function linkBase(): string {
		$base = rtrim((string)$this->config->getSystemValue('files_sharding_master_url', ''), '/');
		if ($base === '') {
			$base = rtrim(\OCP\Server::get(\OCP\IURLGenerator::class)->getAbsoluteURL('/'), '/');
		}
		return $base;
	}

	/** The user's personal public page URL, or null when no email address is set. */
	public function publicPageUrl(string $uid): ?string {
		$email = $this->userManager->get($uid)?->getEMailAddress() ?? '';
		if ($email === '') {
			return null;
		}
		return $this->linkBase() . $this->urlPrefix() . '/users/' . $email;
	}

	/**
	 * Keep the NC profile's "Website" field in sync with the personal public
	 * page: fill it on enable (only when empty or already pointing at a
	 * public page of ours), clear it on disable (only when it is still ours —
	 * a website the user typed in themselves is never touched).
	 */
	private function syncWebsiteProfileField(string $uid, bool $serve): void {
		$user = $this->userManager->get($uid);
		if ($user === null) {
			return;
		}
		$accountManager = \OCP\Server::get(\OCP\Accounts\IAccountManager::class);
		$account  = $accountManager->getAccount($user);
		$property = $account->getProperty(\OCP\Accounts\IAccountManager::PROPERTY_WEBSITE);
		$current  = $property->getValue();
		// Ours = the remote.php form, or the pretty form pointing at this user's own email
		$email    = $user->getEMailAddress() ?? '';
		$isOurs   = str_contains($current, '/remote.php/users/')
			|| str_contains($current, '/remote.php/files_picocms/users/')
			|| ($email !== '' && str_contains($current, '/users/' . $email));

		if ($serve) {
			$url = $this->publicPageUrl($uid);
			if ($url === null || ($current !== '' && !$isOurs) || $current === $url) {
				return;
			}
			$scope = $property->getScope() !== '' ? $property->getScope() : \OCP\Accounts\IAccountManager::SCOPE_LOCAL;
			$account->setProperty(\OCP\Accounts\IAccountManager::PROPERTY_WEBSITE, $url, $scope, \OCP\Accounts\IAccountManager::NOT_VERIFIED);
			$accountManager->updateAccount($account);
		} elseif ($isOurs) {
			$account->setProperty(\OCP\Accounts\IAccountManager::PROPERTY_WEBSITE, '', $property->getScope(), \OCP\Accounts\IAccountManager::NOT_VERIFIED);
			$accountManager->updateAccount($account);
		}
	}

	public function getUserIdFromEmail(string $email): ?string {
		$qb = $this->db->getQueryBuilder();
		$qb->select('userid')
		   ->from('preferences')
		   ->where($qb->expr()->eq('configkey',   $qb->createNamedParameter('email')))
		   ->andWhere($qb->expr()->eq('configvalue', $qb->createNamedParameter($email)));
		$result = $qb->executeQuery();
		$row    = $result->fetch();
		$result->closeCursor();
		return $row ? (string)$row['userid'] : null;
	}

	/**
	 * Create a new personal site: register it in DB and copy sample content.
	 * Returns one of the OK/SITE_NAME_EXISTS/COPY_CONTENT_FAILED constants.
	 */
	public function createPersonalSite(
		string  $uid,
		string  $folder,
		string  $name,
		?string $contentRelPath = null,
		?string $destination    = null,
		?string $theme          = null,
		bool    $copyThemes     = false,
	): int {
		try {
			$userFolder = $this->rootFolder->getUserFolder($uid);
		} catch (\Throwable $e) {
			$this->logger->error('files_picocms: getUserFolder failed for ' . $uid . ': ' . $e->getMessage());
			return self::COPY_CONTENT_FAILED;
		}

		// Never copy sample content into a folder that already has files in it
		// (e.g. a pre-existing /public) — the user must pick another folder or
		// rename the existing one.
		if ($userFolder->nodeExists($folder)) {
			$node = $userFolder->get($folder);
			if (!($node instanceof \OCP\Files\Folder) || count($node->getDirectoryListing()) > 0) {
				return self::FOLDER_NOT_EMPTY;
			}
		}

		// Register the site (skip for /public which is the implicit personal page)
		if ($folder !== '/public') {
			if (!$this->addSite($uid, $folder, $name)) {
				return self::SITE_NAME_EXISTS;
			}
		} else {
			// Creating the personal public page implies consent to serve it —
			// without this the freshly created page would 403 until the user
			// separately finds the "personal public page" toggle.
			$this->setServePublicUrl($uid, true);
		}

		// Ensure the target folder exists
		if (!$userFolder->nodeExists($folder)) {
			$userFolder->newFolder($folder);
		}

		$appDir = dirname(__DIR__, 2); // apps/files_picocms/

		// Copy themes if requested
		if ($copyThemes && $theme !== null) {
			$themesDir = $appDir . '/themes';
			try {
				$destThemesPath = $folder . '/themes';
				if (!$userFolder->nodeExists($destThemesPath)) {
					$userFolder->newFolder($destThemesPath);
				}
				$this->copyDirToUserFolder($themesDir, $destThemesPath, $userFolder);
			} catch (\Throwable $e) {
				$this->logger->error('files_picocms: theme copy failed: ' . $e->getMessage());
				return self::COPY_CONTENT_FAILED;
			}
		}

		// Copy content
		if ($contentRelPath !== null) {
			$srcAbs = $appDir . $contentRelPath;
			$destPath = $folder . ($destination !== null ? '/' . $destination : '');
			$replacements = null;
			if ($theme !== null) {
				$displayName = $this->userManager->get($uid)?->getDisplayName() ?? $uid;
				$replacements = [
					'/^Theme:.*$/m'  => 'Theme: ' . $theme,
					'/^Date:.*$/m'   => 'Date: ' . date('j M Y'),
					'/^Author:.*$/m' => 'Author: ' . $uid,
					'/^Site:.*$/m'   => 'Site: ' . ($theme === 'blog' ? $displayName : ($theme === 'default' ? 'My website' : $name)),
				];
			}
			try {
				if (is_file($srcAbs)) {
					$this->copyFileToUserFolder($srcAbs, $destPath, $userFolder, $replacements);
				} elseif (is_dir($srcAbs)) {
					if (!$userFolder->nodeExists($destPath)) {
						$userFolder->newFolder($destPath);
					}
					$this->copyDirToUserFolder($srcAbs, $destPath, $userFolder, $replacements);
				}
			} catch (\Throwable $e) {
				$this->logger->error('files_picocms: content copy failed: ' . $e->getMessage());
				return self::COPY_CONTENT_FAILED;
			}
		}

		$this->writeDefaultConfig($uid, $folder, $theme === 'default' ? 'My website' : $name, $theme ?? 'default');

		return self::OK;
	}

	private function writeDefaultConfig(string $uid, string $folder, string $name, string $theme): void {
		$appDir      = dirname(__DIR__, 2);
		$displayName = $this->userManager->get($uid)?->getDisplayName() ?? $uid;
		$userFolder  = null;
		$iconRelPath = null;

		try {
			$userFolder = $this->rootFolder->getUserFolder($uid);

			// Use an icon already copied from sample content if present
			foreach (['img/icon.png', 'img/data_icon.png', 'img/favicon.png'] as $candidate) {
				if ($userFolder->nodeExists($folder . '/' . $candidate)) {
					$iconRelPath = $candidate;
					break;
				}
			}

			// Otherwise copy data_icon.png from the theme (or team as fallback)
			if ($iconRelPath === null) {
				$srcIcon = null;
				foreach ([$theme, 'team', 'blog'] as $t) {
					$p = $appDir . '/themes/' . $t . '/img/data_icon.png';
					if (file_exists($p)) { $srcIcon = $p; break; }
				}
				if ($srcIcon !== null) {
					$iconContent = file_get_contents($srcIcon);
					if ($iconContent !== false) {
						$imgDir = $folder . '/img';
						if (!$userFolder->nodeExists($imgDir)) {
							$userFolder->newFolder($imgDir);
						}
						$destIcon = $folder . '/img/data_icon.png';
						if ($userFolder->nodeExists($destIcon)) {
							$userFolder->get($destIcon)->putContent($iconContent);
						} else {
							$userFolder->newFile($destIcon, $iconContent);
						}
						$iconRelPath = 'img/data_icon.png';
					}
				}
			}
		} catch (\Throwable $e) {
			$this->logger->error('files_picocms writeDefaultConfig icon: ' . $e->getMessage());
		}

		// Detect whether the site has separate icon and favicon files
		$userFolder2   = $userFolder ?? null;
		$hasFaviconPng = $userFolder2 && $userFolder2->nodeExists($folder . '/img/favicon.png');
		$faviconPath   = $hasFaviconPng ? 'img/favicon.png' : ($iconRelPath ?? 'img/data_icon.png');
		$navIconPath   = $iconRelPath ?? 'img/data_icon.png';

		$iconSet    = $iconRelPath !== null;
		$iconLine   = $iconSet    ? "icon: {$navIconPath}\n"    : "#icon: {$navIconPath}\n";
		$faviconLine= $iconSet    ? "favicon: {$faviconPath}\n" : "#favicon: {$faviconPath}\n";

		$content = "---\n"
			. "# Site title shown in the browser tab and page header\n"
			. "title: {$name}\n"
			. "\n"
			. "# Pico theme\n"
			. "theme: {$theme}\n"
			. "\n"
			. "# Short description (used in HTML meta tags)\n"
			. "#description: \n"
			. "\n"
			. "# Author name\n"
			. "#author: {$displayName}\n"
			. "\n"
			. "# Access: public (anyone) or private (requires Nextcloud login)\n"
			. "#access: public\n"
			. "\n"
			. "# Icon shown in the theme nav bar — path relative to the site root folder\n"
			. $iconLine
			. "\n"
			. "# Browser tab icon (favicon) — can be a smaller/simpler version of the nav icon\n"
			. $faviconLine
			. "\n"
			. "# Show inline edit links when viewing pages while logged in to Nextcloud\n"
			. "#EditLinks: yes\n"
			. "---\n";

		try {
			$userFolder  = $userFolder ?? $this->rootFolder->getUserFolder($uid);
			$configPath  = $folder . '/_config.md';
			if ($userFolder->nodeExists($configPath)) {
				$userFolder->get($configPath)->putContent($content);
			} else {
				$userFolder->newFile($configPath, $content);
			}
		} catch (\Throwable $e) {
			$this->logger->error('files_picocms writeDefaultConfig: ' . $e->getMessage());
		}
	}

	private function copyDirToUserFolder(string $srcDir, string $destPath, $userFolder, ?array $replacements = null): void {
		$dh = opendir($srcDir);
		while (($file = readdir($dh)) !== false) {
			if ($file === '.' || $file === '..') {
				continue;
			}
			$srcItem  = $srcDir . '/' . $file;
			$destItem = $destPath . '/' . $file;
			if (is_dir($srcItem)) {
				if (!$userFolder->nodeExists($destItem)) {
					$userFolder->newFolder($destItem);
				}
				$this->copyDirToUserFolder($srcItem, $destItem, $userFolder, $replacements);
			} else {
				$this->copyFileToUserFolder($srcItem, $destItem, $userFolder, $replacements);
			}
		}
		closedir($dh);
	}

	/** @return array{content: string, file: string} */
	public function getSiteConfig(string $uid, string $folder): array {
		try {
			$userFolder = $this->rootFolder->getUserFolder($uid);
			foreach ([$folder . '/content/_config.md', $folder . '/_config.md'] as $candidate) {
				if ($userFolder->nodeExists($candidate)) {
					return ['content' => $userFolder->get($candidate)->getContent(), 'file' => $candidate];
				}
			}
			// File doesn't exist yet — report where it will be created
			$file = $userFolder->nodeExists($folder . '/content')
				? $folder . '/content/_config.md'
				: $folder . '/_config.md';
			return ['content' => '', 'file' => $file];
		} catch (\Throwable $e) {
			$this->logger->error('files_picocms getSiteConfig: ' . $e->getMessage());
		}
		return ['content' => '', 'file' => $folder . '/_config.md'];
	}

	public function putSiteConfig(string $uid, string $folder, string $content): bool {
		try {
			$userFolder = $this->rootFolder->getUserFolder($uid);
			$path = $userFolder->nodeExists($folder . '/content')
				? $folder . '/content/_config.md'
				: $folder . '/_config.md';
			if ($userFolder->nodeExists($path)) {
				$userFolder->get($path)->putContent($content);
			} else {
				$userFolder->newFile($path, $content);
			}
			return true;
		} catch (\Throwable $e) {
			$this->logger->error('files_picocms putSiteConfig: ' . $e->getMessage());
			return false;
		}
	}

	private function copyFileToUserFolder(string $srcFile, string $destPath, $userFolder, ?array $replacements = null): void {
		$content = file_get_contents($srcFile);
		if ($content === false) {
			throw new \RuntimeException('Cannot read ' . $srcFile);
		}
		if ($replacements !== null && str_ends_with($srcFile, '.md')) {
			foreach ($replacements as $pattern => $replacement) {
				$content = preg_replace($pattern, $replacement, $content);
			}
		}
		if ($userFolder->nodeExists($destPath)) {
			$userFolder->get($destPath)->putContent($content);
		} else {
			$userFolder->newFile($destPath, $content);
		}
	}
}
