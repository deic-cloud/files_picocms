<?php

declare(strict_types=1);

namespace OCA\FilesPicoCMS\Storage;

use OC\Files\Storage\Wrapper\DirPermissionsMask;
use OCA\FilesPicoCMS\Service\FrozenDatasets;
use OCP\Constants;

/**
 * Makes published datasets read-only on their owner's home storage - for the
 * owner too (see FrozenDatasets for what counts as published).
 *
 * Core's DirPermissionsMask masks one directory; this masks every frozen item,
 * looked up once per request (lazily, on the first check). On top of the mask,
 * a folder that contains a frozen item cannot be deleted - nor moved to
 * another folder, since WebDAV checks delete permission on a move's source.
 * Renaming it in place works; the data and its link are unaffected.
 */
class FrozenDatasetWrapper extends DirPermissionsMask {
	/** @var string[]|null */
	private ?array $frozen = null;
	private FrozenDatasets $frozenDatasets;

	public function __construct($parameters) {
		parent::__construct($parameters + [
			'path' => '',
			'mask' => Constants::PERMISSION_READ | Constants::PERMISSION_SHARE,
		]);
		$this->frozenDatasets = $parameters['frozenDatasets'];
	}

	/** @return string[] */
	private function frozenPaths(): array {
		if ($this->frozen === null) {
			$this->frozen = [];
			try {
				$this->frozen = $this->frozenDatasets->pathsForStorage($this->getCache()->getNumericStorageId());
			} catch (\Throwable $e) {
				\OCP\Server::get(\Psr\Log\LoggerInterface::class)->warning('files_picocms: frozen datasets lookup failed: ' . $e->getMessage());
			}
		}
		return $this->frozen;
	}

	/** Inside (or equal to) a frozen item. */
	protected function checkPath(string $path): bool {
		if ($path === '' || $this->frozen === []) {
			return false;
		}
		foreach ($this->frozenPaths() as $p) {
			if ($path === $p || str_starts_with($path, $p . '/')) {
				return true;
			}
		}
		return false;
	}

	/** A folder that holds a frozen item further down. */
	private function containsFrozen(string $path): bool {
		$prefix = $path === '' ? '' : rtrim($path, '/') . '/';
		foreach ($this->frozenPaths() as $p) {
			if (str_starts_with($p, $prefix)) {
				return true;
			}
		}
		return false;
	}

	public function rmdir($path): bool {
		if ($this->containsFrozen((string)$path)) {
			return false;
		}
		return parent::rmdir($path);
	}

	public function unlink($path): bool {
		if ($this->containsFrozen((string)$path)) {
			return false;
		}
		return parent::unlink($path);
	}

	public function isDeletable($path): bool {
		if ($this->containsFrozen((string)$path)) {
			return false;
		}
		return parent::isDeletable($path);
	}

	// PermissionsMask masks these for every path; only frozen items here.

	public function getMetaData($path): ?array {
		if ($this->checkPath((string)$path)) {
			return parent::getMetaData($path);
		}
		return $this->getWrapperStorage()->getMetaData($path);
	}

	public function getDirectoryContent($directory): \Traversable {
		$dir = trim((string)$directory, '/');
		foreach ($this->getWrapperStorage()->getDirectoryContent($directory) as $data) {
			$path = $dir === '' ? $data['name'] : $dir . '/' . $data['name'];
			if (isset($data['permissions']) && $this->checkPath($path)) {
				$data['scan_permissions'] ??= $data['permissions'];
				$data['permissions'] &= $this->mask;
			}
			yield $data;
		}
	}
}
