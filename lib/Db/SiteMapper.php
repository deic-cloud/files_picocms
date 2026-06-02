<?php

declare(strict_types=1);

namespace OCA\FilesPicoCMS\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class SiteMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'files_picocms', Site::class);
	}

	/** @return Site[] */
	public function findByUid(string $uid): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
		   ->from($this->getTableName())
		   ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)));
		return $this->findEntities($qb);
	}

	public function findByName(string $site): ?Site {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
		   ->from($this->getTableName())
		   ->where($qb->expr()->eq('site', $qb->createNamedParameter($site)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function findByUidAndPath(string $uid, string $path): ?Site {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
		   ->from($this->getTableName())
		   ->where($qb->expr()->eq('uid',  $qb->createNamedParameter($uid)))
		   ->andWhere($qb->expr()->eq('path', $qb->createNamedParameter($path)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function deleteByUidAndPath(string $uid, string $path): int {
		$qb = $this->db->getQueryBuilder();
		return $qb->delete($this->getTableName())
		          ->where($qb->expr()->eq('uid',  $qb->createNamedParameter($uid)))
		          ->andWhere($qb->expr()->eq('path', $qb->createNamedParameter($path)))
		          ->executeStatement();
	}
}
