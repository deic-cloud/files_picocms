<?php

declare(strict_types=1);

namespace OCA\FilesPicoCMS\Service;

use OCP\IDBConnection;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * Which items are frozen (read-only, for the owner too): published datasets.
 *
 *  - an item whose public link is listed in the catalogue
 *    (share attribute files_picocms:catalog_listed) - unlisting unfreezes it;
 *  - an item with a DOI in its DataCite record (meta_data schema on the
 *    system tag "DataCite", key "doi", written by files_publish) - a DOI promises the data behind it
 *    does not change, so this holds even when the link is unlisted or deleted.
 *
 * Enforced by FrozenDatasetWrapper on the owner's home storage, so it applies
 * to every way a write arrives (web, WebDAV, sync clients, jobs, collaborators).
 * Metadata lives in the database and stays editable.
 */
class FrozenDatasets {
	public function __construct(
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Storage-relative paths (files/...) of the frozen items on a storage.
	 *
	 * @return string[]
	 */
	public function pathsForStorage(int $storageId): array {
		$paths = [];

		$qb = $this->db->getQueryBuilder();
		$qb->select('fc.path', 's.attributes')
			->from('share', 's')
			->innerJoin('s', 'filecache', 'fc', $qb->expr()->eq('fc.fileid', 's.file_source'))
			->where($qb->expr()->eq('s.share_type', $qb->createNamedParameter(IShare::TYPE_LINK)))
			->andWhere($qb->expr()->eq('fc.storage', $qb->createNamedParameter($storageId)))
			->andWhere($qb->expr()->like('s.attributes', $qb->createNamedParameter('%catalog_listed%')));
		$res = $qb->executeQuery();
		while ($row = $res->fetch()) {
			if (CatalogService::attrListed((string)$row['attributes'])) {
				$paths[] = (string)$row['path'];
			}
		}
		$res->closeCursor();

		foreach ($this->doiRows('fc.storage', $storageId, 'fc.path') as $path) {
			$paths[] = (string)$path;
		}

		return array_values(array_unique($paths));
	}

	/** Whether the item has a DOI in its DataCite record. */
	public function hasDoi(int $fileId): bool {
		return $this->doiRows('fc.fileid', $fileId, 'fc.fileid') !== [];
	}

	/** Values of $column for items with a recorded DOI, where $where = $value. */
	private function doiRows(string $where, int $value, string $column): array {
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select($column)
				->from('meta_data_docKeys', 'dk')
				->innerJoin('dk', 'meta_data_keys', 'k', $qb->expr()->eq('k.id', 'dk.keyid'))
				->innerJoin('dk', 'systemtag', 't', $qb->expr()->eq('t.id', 'dk.tagid'))
				->innerJoin('dk', 'filecache', 'fc', $qb->expr()->eq('fc.fileid', 'dk.fileid'))
				->where($qb->expr()->eq('t.name', $qb->createNamedParameter('DataCite')))
				->andWhere($qb->expr()->eq('k.name', $qb->createNamedParameter('doi')))
				->andWhere($qb->expr()->neq('dk.value', $qb->createNamedParameter('')))
				->andWhere($qb->expr()->eq($where, $qb->createNamedParameter($value)));
			$res = $qb->executeQuery();
			$out = $res->fetchAll(\PDO::FETCH_COLUMN);
			$res->closeCursor();
			return $out;
		} catch (\Throwable $e) {
			// meta_data not installed (no tables): no DOIs recorded here
			$this->logger->debug('files_picocms: no DOI lookup: ' . $e->getMessage());
			return [];
		}
	}
}
