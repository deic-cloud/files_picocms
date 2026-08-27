<?php

declare(strict_types=1);

namespace OCA\FilesPicoCMS\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IURLGenerator;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * The public dataset/notebook catalog: link shares whose owner opted in
 * (share attribute files_picocms:catalog_listed).
 *
 * localEntries() collects THIS node's entries, enriched with owner display
 * name, kind (dataset folder / Jupyter notebook / file via mimetype), tag
 * names (meta_data rides NC systemtags) and user metadata (meta_data docKeys —
 * guarded, the app may be absent). Each entry's URL is absolute to this node,
 * so aggregated entries keep pointing at their owning silo.
 *
 * clusterEntries() is the aggregate: on the MASTER, own entries + every
 * registered silo's (internal/catalog); on a silo, the master's aggregate
 * (internal/catalog-all). Fully guarded — without files_sharding (standalone
 * install) it degrades to localEntries(). Callers cache (serve.php, 600s).
 */
class CatalogService {
	public function __construct(
		private IDBConnection   $db,
		private IURLGenerator   $urlGenerator,
		private IUserManager    $userManager,
		private LoggerInterface $logger,
	) {
	}

	/** @return array<int, array<string, mixed>> */
	public function localEntries(): array {
		$out = [];
		try {
			$q = $this->db->getQueryBuilder();
			// share_type 3 = TYPE_LINK; the LIKE is a cheap prefilter — the
			// authoritative check is the JSON parse below.
			$q->select('uid_owner', 'file_source', 'file_target', 'token', 'stime', 'label', 'attributes')
				->from('share')
				->where($q->expr()->eq('share_type', $q->createNamedParameter(3, IQueryBuilder::PARAM_INT)))
				->andWhere($q->expr()->isNotNull('token'))
				->andWhere($q->expr()->like('attributes', $q->createNamedParameter('%files_picocms%')))
				->orderBy('stime', 'DESC')
				->setMaxResults(200);
			$res = $q->executeQuery();
			$rows = $res->fetchAll();
			$res->closeCursor();

			$fileIds = array_values(array_filter(array_map(static fn ($r) => (int)($r['file_source'] ?? 0), $rows)));

			// mimetype per shared node → kind
			$mimes = [];
			if ($fileIds !== []) {
				try {
					$mq = $this->db->getQueryBuilder();
					$mq->select('fc.fileid', 'm.mimetype')
						->from('filecache', 'fc')
						->innerJoin('fc', 'mimetypes', 'm', $mq->expr()->eq('fc.mimetype', 'm.id'))
						->where($mq->expr()->in('fc.fileid', $mq->createNamedParameter($fileIds, IQueryBuilder::PARAM_INT_ARRAY)));
					$mr = $mq->executeQuery();
					while (($m = $mr->fetch()) !== false) {
						$mimes[(int)$m['fileid']] = (string)$m['mimetype'];
					}
					$mr->closeCursor();
				} catch (\Throwable) {
				}
			}

			// user metadata (meta_data app; guarded)
			$meta = [];
			if ($fileIds !== []) {
				try {
					$kq = $this->db->getQueryBuilder();
					$kq->select('d.fileid', 'k.name', 'd.value')
						->from('meta_data_docKeys', 'd')
						->innerJoin('d', 'meta_data_keys', 'k', $kq->expr()->eq('d.keyid', 'k.id'))
						->where($kq->expr()->in('d.fileid', $kq->createNamedParameter($fileIds, IQueryBuilder::PARAM_INT_ARRAY)))
						->andWhere($kq->expr()->neq('d.value', $kq->createNamedParameter('')));
					$kr = $kq->executeQuery();
					while (($k = $kr->fetch()) !== false) {
						$meta[(int)$k['fileid']][(string)$k['name']] = (string)$k['value'];
					}
					$kr->closeCursor();
				} catch (\Throwable) {
				}
			}

			// tags per fileid (meta_data rides NC systemtags)
			$tags = [];
			if ($fileIds !== []) {
				try {
					$tq = $this->db->getQueryBuilder();
					$tq->select('om.objectid', 'st.name')
						->from('systemtag_object_mapping', 'om')
						->innerJoin('om', 'systemtag', 'st', $tq->expr()->eq('om.systemtagid', 'st.id'))
						->where($tq->expr()->eq('om.objecttype', $tq->createNamedParameter('files')))
						->andWhere($tq->expr()->in('om.objectid', $tq->createNamedParameter(array_map('strval', $fileIds), IQueryBuilder::PARAM_STR_ARRAY)));
					$tr = $tq->executeQuery();
					while (($t = $tr->fetch()) !== false) {
						$tags[(int)$t['objectid']][] = (string)$t['name'];
					}
					$tr->closeCursor();
				} catch (\Throwable) {
				}
			}

			foreach ($rows as $row) {
				if (!self::attrListed((string)($row['attributes'] ?? ''))) {
					continue;
				}
				$token = (string)($row['token'] ?? '');
				if ($token === '') {
					continue;
				}
				$label = trim((string)($row['label'] ?? ''));
				$title = $label !== '' ? $label : trim(basename((string)($row['file_target'] ?? '')), '/');
				if ($title === '') {
					$title = 'Shared folder';
				}
				$owner = (string)($row['uid_owner'] ?? '');
				$at    = strrpos($owner, '@');
				$ownerName = $owner;
				try {
					$u = $this->userManager->get($owner);
					if ($u !== null) {
						$ownerName = $u->getDisplayName() ?: $owner;
					}
				} catch (\Throwable) {
				}
				$fid  = (int)($row['file_source'] ?? 0);
				$mime = $mimes[$fid] ?? '';
				$kind = $mime === 'httpd/unix-directory' ? 'dataset'
					: ($mime === 'application/x-ipynb+json' ? 'notebook' : 'file');
				$out[] = [
					'title'       => $title,
					'url'         => $this->urlGenerator->linkToRouteAbsolute('files_sharing.sharecontroller.showShare', ['token' => $token]),
					'owner'       => $owner,
					'owner_name'  => $ownerName,
					'institution' => $at !== false ? strtolower(substr($owner, $at + 1)) : '',
					'stime'       => (int)($row['stime'] ?? 0),
					'kind'        => $kind,
					'tags'        => $tags[$fid] ?? [],
					'meta'        => $meta[$fid] ?? [],
				];
			}
		} catch (\Throwable $e) {
			$this->logger->warning('files_picocms: catalog localEntries: ' . $e->getMessage());
		}
		return $out;
	}

	/** @return array<int, array<string, mixed>> cluster-wide (best effort) */
	public function clusterEntries(): array {
		$local = $this->localEntries();
		if (!class_exists(\OCA\FilesSharding\Service\ShardingService::class)
			|| !class_exists(\OCA\FilesSharding\Service\InterServerClient::class)) {
			return $local;
		}
		try {
			$sharding = \OCP\Server::get(\OCA\FilesSharding\Service\ShardingService::class);
			$client   = \OCP\Server::get(\OCA\FilesSharding\Service\InterServerClient::class);

			if ($sharding->isMaster()) {
				$all = $local;
				foreach ($sharding->getAllServers() as $server) {
					if ($sharding->isSelf($server)) {
						continue;
					}
					$data = $client->getDirect(
						rtrim($sharding->apiUrlForServer($server), '/'),
						'internal/catalog', [], 'files_picocms');
					if (is_array($data) && isset($data['entries']) && is_array($data['entries'])) {
						$all = array_merge($all, $data['entries']);
					}
				}
				return self::sortDedup($all);
			}

			// Silo: the master serves the aggregate (which includes this node —
			// internal/catalog is local-only, so there is no recursion).
			$master = $sharding->masterInternalUrl();
			if ($master !== '') {
				$data = $client->getDirect($master, 'internal/catalog-all', [], 'files_picocms');
				if (is_array($data) && isset($data['entries']) && is_array($data['entries'])) {
					return self::sortDedup($data['entries']);
				}
			}
		} catch (\Throwable $e) {
			$this->logger->warning('files_picocms: catalog clusterEntries: ' . $e->getMessage());
		}
		return $local; // transient failure → node-local view, never an empty page
	}

	/** @param array<int, array<string, mixed>> $entries */
	private static function sortDedup(array $entries): array {
		$seen = [];
		$out  = [];
		foreach ($entries as $e) {
			$key = (string)($e['url'] ?? '');
			if ($key === '' || isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;
			$out[] = $e;
		}
		usort($out, static fn ($a, $b) => ((int)($b['stime'] ?? 0)) <=> ((int)($a['stime'] ?? 0)));
		return array_slice($out, 0, 200);
	}

	/** True if the share attributes JSON carries a truthy files_picocms:catalog_listed. */
	public static function attrListed(string $json): bool {
		if ($json === '') {
			return false;
		}
		$attrs = json_decode($json, true);
		if (!is_array($attrs)) {
			return false;
		}
		foreach ($attrs as $a) {
			if (!is_array($a)) {
				continue;
			}
			// Two serializations exist: tuple form [scope, key, value] (what NC34
			// writes to oc_share.attributes) and object form {scope,key,enabled|value}.
			if (array_is_list($a) && count($a) >= 3) {
				if ($a[0] === 'files_picocms' && $a[1] === 'catalog_listed') {
					return (bool)$a[2];
				}
				continue;
			}
			if (($a['scope'] ?? '') === 'files_picocms' && ($a['key'] ?? '') === 'catalog_listed') {
				return (bool)($a['enabled'] ?? $a['value'] ?? false);
			}
		}
		return false;
	}
}
