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
		if ($rename) {
			$existing = $this->mapper->findByUidAndPath($uid, $path);
			if ($existing === null) {
				return false;
			}
			$existing->setSite($name);
			$this->mapper->update($existing);
			return true;
		}
		$site = new Site();
		$site->setUid($uid);
		$site->setSite($name);
		$site->setPath($path);
		$site->setGid($gid);
		$this->mapper->insert($site);
		return true;
	}

	public function removeSite(string $uid, string $path): bool {
		return $this->mapper->deleteByUidAndPath($uid, $path) > 0;
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
		// Register the site (skip for /public which is the implicit personal page)
		if ($folder !== '/public') {
			if (!$this->addSite($uid, $folder, $name)) {
				return self::SITE_NAME_EXISTS;
			}
		}

		try {
			$userFolder = $this->rootFolder->getUserFolder($uid);
		} catch (\Throwable $e) {
			$this->logger->error('files_picocms: getUserFolder failed for ' . $uid . ': ' . $e->getMessage());
			return self::COPY_CONTENT_FAILED;
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
					'/^Site:.*$/m'   => 'Site: ' . ($theme === 'blog' ? $displayName : 'Sample Site'),
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

		$this->writeDefaultConfig($uid, $folder, $name, $theme ?? 'default');

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

			// Otherwise copy data_icon.png from the theme (or wiki as fallback)
			if ($iconRelPath === null) {
				$srcIcon = null;
				foreach ([$theme, 'wiki', 'blog'] as $t) {
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
