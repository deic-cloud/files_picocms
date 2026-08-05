<?php

/**
 * Pico CMS site rendering endpoint.
 *
 * URL scheme (relative to NC webroot):
 *   remote.php/files_picocms/sites/{name}[/{path}]  → named site
 *   remote.php/files_picocms/users/{email}[/{path}]  → user public page
 *
 * Per-site config lives in _config.md at the site (or sub-directory) root.
 * The leading underscore prevents Pico from serving it as a page.
 * Frontmatter keys recognised here:
 *   access:     public (default) | private
 *   theme:      theme directory name
 *   EditLinks: yes | no
 *   title:      overrides the DB site name
 *   description: site description passed to Pico
 *   favicon:    path to favicon relative to site root (e.g. img/favicon.png)
 *
 * Access: "private" requires an active NC session. Unauthenticated visitors
 * receive HTTP 403 and see the site's content/403.md if present, or a plain
 * fallback page with a link to the NC login screen.
 */

declare(strict_types=1);

use OCA\FilesPicoCMS\Service\SiteService;
use OCP\IConfig;
use Symfony\Component\Yaml\Yaml;

// ── Load Pico and its dependencies ───────────────────────────────────────────
require_once __DIR__ . '/../3rdparty/bootstrap.php';

// ── Parse request URL ─────────────────────────────────────────────────────────

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$uriPath    = strtok($requestUri, '?') ?: '';

// Strip webroot if present (e.g. /nextcloud/remote.php/... → /remote.php/...)
$webRoot = \OC::$WEBROOT;
if ($webRoot !== '' && str_starts_with($uriPath, $webRoot)) {
	$uriPath = substr($uriPath, strlen($webRoot));
}

// $uriPath is now e.g. /remote.php/files_picocms/sites/mysite/themes/x.css,
// /remote.php/sites/mysite/… (pretty-URL remote services), or /sites/mysite/…
// (REQUEST_URI of a webserver-rewritten pretty URL).
$localPath = $uriPath;
foreach (['/remote.php/files_picocms', '/remote.php'] as $prefix) {
	if (str_starts_with($localPath, $prefix)) {
		$localPath = substr($localPath, strlen($prefix));
		break;
	}
}
// $localPath = /sites/mysite/themes/x.css  or  /users/someone@example.com

$siteName  = null;
$userEmail = null;
$sitePath  = '';

if (preg_match('|^/sites/([^/]+)(/.*)?$|', $localPath, $m)) {
	$siteName = urldecode($m[1]);
	$sitePath = isset($m[2]) ? ltrim($m[2], '/') : '';
} elseif (preg_match('|^/users/([^/]+)(/.*)?$|', $localPath, $m)) {
	$userEmail = urldecode($m[1]);
	$sitePath  = isset($m[2]) ? ltrim($m[2], '/') : '';
} else {
	http_response_code(404);
	exit;
}

// REQUEST_URI is percent-encoded — decode so filesystem lookups (assets and
// the write/raw proxies) work for names with spaces or non-ASCII characters.
$sitePath = rawurldecode($sitePath);

// ── Rewrite self-probe ────────────────────────────────────────────────────────
// PrettyUrlsCheck (setup check) GETs /sites/__picocms_probe to verify the
// webserver rewrite (/sites → /remote.php/sites) actually reaches this endpoint.
// Answer with a stable marker before any lookup/auth. If the rewrite is missing
// the request never gets here — Nextcloud returns its own 404 — and the check warns.
if ($siteName === '__picocms_probe') {
	header('Content-Type: text/plain; charset=utf-8');
	header('X-Picocms-Probe: ok');
	http_response_code(200);
	echo 'files_picocms:rewrite-ok';
	exit;
}

// ── Resolve site info ─────────────────────────────────────────────────────────

/** @var SiteService $siteService */
$siteService = \OC::$server->get(SiteService::class);
/** @var IConfig $config */
$config = \OC::$server->get(IConfig::class);

// URL prefix for generated links — '' when the web server rewrites /sites
// and /users to remote.php (config: files_picocms.url_prefix; see README).
$urlPrefix = rtrim((string)$config->getSystemValue('files_picocms.url_prefix', '/remote.php/files_picocms'), '/');

$siteInfo = null;

if ($userEmail !== null) {
	$uid = $siteService->getUserIdFromEmail($userEmail);
	if ($uid === null) {
		http_response_code(404);
		exit;
	}
	// NOTE: the servepublicurl opt-in is checked AFTER the silo redirect below —
	// it is per-node user config and only the user's home node has it set, so
	// checking it here would 403 on the master before redirecting.
	$siteInfo = [
		'uid'  => $uid,
		'path' => '/public',
		'site' => 'Public page',
		'gid'  => '',
	];
	$baseUrl = 'https://' . $_SERVER['HTTP_HOST'] . $webRoot . $urlPrefix . '/users/' . urlencode($userEmail);
} else {
	$siteInfo = $siteService->lookupSite($siteName);
	if ($siteInfo === null) {
		// Not in the local registry — the master holds the authoritative copy.
		// On a silo: ask the master where the site lives. On the master: our own
		// registry is authoritative, but it can go stale if a silo→master forward
		// was lost during a transient master outage — so heal by asking the silos
		// we manage, then backfill locally so future lookups resolve without a hop.
		$remote = _pico_master_lookup($config, $siteName);
		if ($remote === null) {
			$remote = _pico_silo_broadcast_lookup($config, $siteName);
			if ($remote !== null) {
				try {
					$siteService->addSite($remote['uid'], $remote['path'], $siteName, $remote['gid'] ?? '');
				} catch (\Throwable $e) {
					error_log('files_picocms heal backfill: ' . $e->getMessage());
				}
			}
		}
		if ($remote === null) {
			http_response_code(404);
			exit;
		}
		$thisBase = 'https://' . $_SERVER['HTTP_HOST'] . $webRoot;
		$target   = !empty($remote['server_url'])
			? rtrim($remote['server_url'], '/')
			: rtrim((string)$config->getSystemValue('files_sharding_master_url', ''), '/');
		if ($target !== '' && $target !== $thisBase) {
			header('Location: ' . $target . $_SERVER['REQUEST_URI']);
			http_response_code(307);
			exit;
		}
		// Stale local registry (master says the site is here) — serve with the master's row.
		$siteInfo = $remote;
	}
	$baseUrl = 'https://' . $_SERVER['HTTP_HOST'] . $webRoot . $urlPrefix . '/sites/' . urlencode($siteName);
}

$uid = $siteInfo['uid'];

// ── files_sharding: master redirects to the silo hosting the site ────────────
// Only the master holds the user→silo assignment table; silos resolve foreign
// sites through _pico_master_lookup above. A site owner without an assignment
// is homed on the master itself — serve locally.

if (class_exists(\OCA\FilesSharding\Service\ShardingService::class)) {
	try {
		$shardingService = \OCP\Server::get(\OCA\FilesSharding\Service\ShardingService::class);
		if ($shardingService->isMaster()) {
			$ownerServer = $shardingService->getUserServer($uid);
			$ownerUrl    = $ownerServer ? rtrim($ownerServer->getUrl(), '/') : '';
			if ($ownerUrl !== '' && $ownerUrl !== 'https://' . $_SERVER['HTTP_HOST'] . $webRoot) {
				header('Location: ' . $ownerUrl . $_SERVER['REQUEST_URI']);
				http_response_code(307);
				exit;
			}
		}
	} catch (\Throwable $e) {
		error_log('files_picocms silo redirect: ' . $e->getMessage());
	}
}

// Personal pages: the opt-in flag is enforced by the node that actually
// serves the page (per-node user config — see note at the lookup above).
if ($userEmail !== null && !$siteService->getServePublicUrl($uid)) {
	http_response_code(403);
	exit;
}

// ── Determine master login base URL ──────────────────────────────────────────

$scheme     = \OC::$server->get(\OCP\IRequest::class)->getServerProtocol();
$masterBase = rtrim((string)$config->getSystemValue('files_sharding_master_url', ''), '/');
if ($masterBase === '') {
	$masterBase = $scheme . '://' . $_SERVER['HTTP_HOST'] . $webRoot;
}

// ── Build filesystem paths ────────────────────────────────────────────────────

$dataDir = $config->getSystemValue('datadirectory', '');
if ($dataDir === '') {
	http_response_code(500);
	exit;
}

$gid = $siteInfo['gid'] ?? '';
if ($gid !== '') {
	$siteFsPath = $dataDir . '/' . $uid . '/files/.uga_grants/' . $gid . $siteInfo['path'];
} else {
	$siteFsPath = $dataDir . '/' . $uid . '/files' . $siteInfo['path'];
}

if (!is_dir($siteFsPath)) {
	http_response_code(404);
	exit;
}

// ── Override NC's restrictive CSP with one suitable for website serving ───────
// connect-src must cover all silos (same hostname, different ports) so WebDAV
// writes from the blog JS can reach the user's home silo.
// All theme assets (CSS, JS, fonts) are bundled locally — no CDN allowances.
$cspHost = parse_url($scheme . '://' . $_SERVER['HTTP_HOST'], PHP_URL_HOST) ?: $_SERVER['HTTP_HOST'];
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self' https://{$cspHost}:*; frame-ancestors 'self'");

// ── Determine content directory ───────────────────────────────────────────────

$appDir = dirname(__DIR__);

// Use content/ subdirectory only if it exists AND has an index.md (i.e. is not just an empty leftover dir)
if (is_dir($siteFsPath . '/content') && is_file($siteFsPath . '/content/index.md')) {
	$contentDir = $siteFsPath . '/content';
} else {
	$contentDir = $siteFsPath;
}

// ── Serve theme files early (always public, even for private sites) ──────────

if (str_starts_with($sitePath, 'themes/')) {
	$themeFile = $siteFsPath . '/' . $sitePath;
	if (!file_exists($themeFile)) {
		$themeFile = $appDir . '/' . $sitePath;
	}
	$ext = strtolower(pathinfo($sitePath, PATHINFO_EXTENSION));
	if (file_exists($themeFile)) {
		header('Content-Type: ' . _pico_mime($ext));
		_pico_cache_headers($themeFile, 86400);
		readfile($themeFile);
	} else {
		http_response_code(404);
	}
	exit;
}

// ── Serve the bundled MathJax (shared app-level copy, never in site folders) ──

if (str_starts_with($sitePath, 'mathjax/')) {
	$mjFile = $appDir . '/3rdparty/' . $sitePath;
	$ext    = strtolower(pathinfo($sitePath, PATHINFO_EXTENSION));
	if (strpos($sitePath, '..') === false && file_exists($mjFile) && is_file($mjFile)) {
		header('Content-Type: ' . _pico_mime($ext));
		_pico_cache_headers($mjFile, 604800);
		readfile($mjFile);
	} else {
		http_response_code(404);
	}
	exit;
}

// ── Read _config.md and enforce access ───────────────────────────────────────

$siteConfig = _pico_site_config($contentDir, $sitePath);
$access     = strtolower(trim($siteConfig['access'] ?? 'public'));

if ($access === 'private') {
	$loggedIn = false;
	try {
		$loggedIn = \OC::$server->get(\OCP\IUserSession::class)->isLoggedIn();
	} catch (\Throwable) {}

	if (!$loggedIn) {
		http_response_code(403);
		if (file_exists($contentDir . '/403.md')) {
			// Render the site's own 403 page through Pico (themed)
			$_SERVER['QUERY_STRING'] = '403';
			// fall through to Pico rendering below
		} else {
			header('Content-Type: text/html; charset=utf-8');
			$loginUrl = $masterBase . '/index.php/login?redirect_url=' . urlencode($_SERVER['REQUEST_URI']);
			echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>403 Forbidden</title></head>'
			   . '<body><h1>403 Forbidden</h1>'
			   . '<p>This page is private. <a href="' . htmlspecialchars($loginUrl) . '">Log in</a></p>'
			   . '</body></html>';
			exit;
		}
	}
}

// ── Determine themes directory ────────────────────────────────────────────────

$hasThemes = is_dir($siteFsPath . '/themes');
$themesDir = $hasThemes ? $siteFsPath . '/themes/' : $appDir . '/themes/';

// ── Handle raw asset requests (themes, images, CSS, JS, etc.) ────────────────

$extension = $sitePath !== '' ? strtolower(pathinfo($sitePath, PATHINFO_EXTENSION)) : '';

// Serve image/binary/font files directly (reads only — PUT/DELETE on these
// paths must fall through to the write/delete proxies below)
$isReadRequest = in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true);
$rawExtensions = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'pdf', 'nb', 'mp4', 'webm'];
if ($isReadRequest && $sitePath !== '' && in_array($extension, $rawExtensions, true)) {
	$filePath = $siteFsPath . '/' . $sitePath;
	if (!file_exists($filePath)) {
		$filePath = $contentDir . '/' . $sitePath;
	}
	if (file_exists($filePath)) {
		header('Content-Type: ' . _pico_mime($extension));
		$ttl = in_array($extension, ['woff', 'woff2', 'ttf', 'eot'], true) ? 604800 : 3600;
		_pico_cache_headers($filePath, $ttl);
		readfile($filePath);
	} else {
		http_response_code(404);
	}
	exit;
}

// Serve HTML/CSS/JS if a non-Pico site (index.html present, no index.md)
if ($sitePath === '' && !file_exists($contentDir . '/index.md') && file_exists($siteFsPath . '/index.html')) {
	$_SERVER['QUERY_STRING'] = 'index.html';
}

if ($isReadRequest && in_array($extension, ['html', 'css', 'js'], true)) {
	$filePath = $siteFsPath . '/' . $sitePath;
	if (!file_exists($filePath)) {
		$filePath = $contentDir . '/' . $sitePath;
	}
	if (file_exists($filePath)) {
		header('Content-Type: ' . _pico_mime($extension));
		_pico_cache_headers($filePath, 3600);
		readfile($filePath);
	} else {
		http_response_code(404);
	}
	exit;
}

// ── Run Pico ──────────────────────────────────────────────────────────────────

// Block direct requests to _ prefixed files (e.g. _config.md — config only, not content)
if ($sitePath !== '' && substr(ltrim(basename($sitePath), '/'), 0, 1) === '_') {
	http_response_code(404);
	exit;
}

// Always give Pico the clean path — $_GET is already populated by PHP and is unaffected
if ($sitePath !== '') {
	$_SERVER['QUERY_STRING'] = $sitePath;
}

$picoConfig = [
	'base_url'          => $baseUrl,
	'base_uri'          => $webRoot . $urlPrefix . '/' . ($siteName !== null ? 'sites/' . urlencode($siteName) : 'users/' . urlencode($userEmail)),
	'content_dir'       => $contentDir,
	'rewrite_url'       => true,
	'site_title'        => $siteConfig['title'] ?? $siteInfo['site'],
	'user'              => $uid,
	'group'             => $gid,
	'pages_order_by'    => 'date',
	'pagination'        => -1,
	'pagination_limit'  => 10,
	'toc_top_txt'       => '',
];

// Merge optional _config.md keys into Pico config
if (!empty($siteConfig['theme'])) {
	$picoConfig['theme'] = $siteConfig['theme'];
}
if (!empty($siteConfig['description'])) {
	$picoConfig['description'] = $siteConfig['description'];
}
if (isset($siteConfig['EditLinks'])) {
	$picoConfig['edit_links'] = (strtolower((string)$siteConfig['EditLinks']) === 'yes');
}
$iconVal = $siteConfig['icon'] ?? $siteConfig['Icon'] ?? null;
if (!empty($iconVal)) {
	$picoConfig['icon']    = $iconVal;
	$picoConfig['favicon'] = $picoConfig['favicon'] ?? $iconVal;
}
if (!empty($siteConfig['favicon'])) {
	$picoConfig['favicon'] = $siteConfig['favicon'];
}

// Login URL for themes (e.g. "log in" link on 403 pages)
$picoConfig['login_url']      = $masterBase . '/index.php/login?redirect_url=' . urlencode($_SERVER['REQUEST_URI']);
$picoConfig['original_path']  = $sitePath;
// Site folder relative to the owner's files root — used by themes to link
// into the NC Files app (e.g. the team-site Manage button).
$picoConfig['site_folder']    = $siteInfo['path'];

// Provide request token for authenticated calls within the site (e.g. avatars)
try {
	$picoConfig['requesttoken'] = \OC::$server->get(\OCP\ISession::class)->get('requesttoken') ?? '';
} catch (\Throwable) {
	$picoConfig['requesttoken'] = '';
}

$pico = new Pico(
	$appDir . '/3rdparty/Pico',  // root dir (plugins/ etc. relative to this)
	'config/',
	'plugins/',
	$themesDir,
	$uid
);

$pico->ocOwner = $uid;
// WebDAV writes must go to this silo (where the files live), not to master.
// Using master's URL would cause cross-origin CORS failures for blogs on silos.
$pico->ocUserHomeUrl = rtrim($scheme . '://' . $_SERVER['HTTP_HOST'] . $webRoot, '/');
// picocms base URL — used by JS as the write proxy base (same-origin, no CORS).
$pico->ocCmsBase = $baseUrl;

// Grant write/edit access based on NC ownership or share permissions.
// ocPath is now site-relative (e.g. "my-post.md"), used by the picocms proxy URL.
$writeGranted = false;
$currentUid = '';
try {
	$currentUser = \OC::$server->get(\OCP\IUserSession::class)->getUser();
	$currentUid  = $currentUser ? $currentUser->getUID() : '';
	if ($currentUid !== '') {
		$filesRoot       = $dataDir . '/' . $uid . '/files';
		$contentRelative = ltrim(substr($contentDir, strlen($filesRoot)), '/');
		// Editable file behind the current page: site root and directory URLs
		// map to their index.md (if one exists); page URLs map to {page}.md.
		// A directory without index.md keeps a trailing slash so themes can
		// offer "create the index page here".
		if ($sitePath === '') {
			$ocPath = file_exists($contentDir . '/index.md') ? 'index.md' : '';
		} elseif (is_dir($contentDir . '/' . $sitePath)) {
			$dirPath = rtrim($sitePath, '/');
			$ocPath  = file_exists($contentDir . '/' . $dirPath . '/index.md')
				? $dirPath . '/index.md'
				: $dirPath . '/';
		} else {
			$ocPath = $sitePath . '.md';
		}

		if ($currentUid === $uid) {
			// Site owner: full edit access
			$writeGranted = true;
			$pico->setOwnerEditMode($ocPath);
		} else {
			try {
				$rootFolder  = \OC::$server->get(\OCP\Files\IRootFolder::class);
				$siteNode    = $rootFolder->getUserFolder($uid)->get($contentRelative);
				$nodeId      = $siteNode->getId();
				$found       = false;

				// Strategy 1: local mount lookup (same silo)
				foreach ($rootFolder->getUserFolder($currentUid)->getById($nodeId) as $node) {
					if ($node->isUpdateable()) {
						$writeGranted = true;
						$pico->setWriteAccess($ocPath);
						$found = true;
						break;
					}
				}

				// Strategy 2: outgoing shares from site owner (cross-silo federated shares)
				if (!$found) {
					$shareManager = \OC::$server->get(\OCP\Share\IManager::class);
					$cloudIdMgr   = \OC::$server->get(\OCP\Federation\ICloudIdManager::class);
					foreach ([\OCP\Share\IShare::TYPE_REMOTE, \OCP\Share\IShare::TYPE_USER] as $type) {
						foreach ($shareManager->getSharesBy($uid, $type, $siteNode, false, -1) as $share) {
							if (!($share->getPermissions() & \OCP\Constants::PERMISSION_UPDATE)) {
								continue;
							}
							try {
								$sharedUser = $cloudIdMgr->resolveCloudId($share->getSharedWith())->getUser();
							} catch (\Throwable) {
								$sharedUser = strstr($share->getSharedWith(), '@', true) ?: $share->getSharedWith();
							}
							if ($sharedUser === $currentUid) {
								$writeGranted = true;
								$pico->setWriteAccess($ocPath);
								$found = true;
								break 2;
							}
						}
					}
				}
			} catch (\Throwable $e) {
				error_log('files_picocms share detection: ' . $e->getMessage());
			}
		}
	}
} catch (\Throwable $e) {
	error_log('files_picocms permission setup: ' . $e->getMessage());
}

// ── Write proxy (PUT) ─────────────────────────────────────────────────────────
// Handles file writes for all users (owner and shared). Writes as the site owner
// via the NC Files API, keeping the file cache current. Same-origin → no CORS.
// Markdown for page edits, plus media/document types for the theme upload button.
$putExtensions = ['md', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'pdf', 'mp4', 'webm'];
if ($_SERVER['REQUEST_METHOD'] === 'PUT' && $sitePath !== '') {
	if (!$writeGranted
		|| strpos($sitePath, '..') !== false
		|| strpos($sitePath, "\0") !== false
		|| !in_array(strtolower(pathinfo($sitePath, PATHINFO_EXTENSION)), $putExtensions, true)
	) {
		http_response_code(403);
		exit;
	}
	try {
		$filesRoot  = $dataDir . '/' . $uid . '/files';
		$relPath    = ltrim(substr($contentDir . '/' . $sitePath, strlen($filesRoot)), '/');
		$body       = (string)file_get_contents('php://input');
		$userFolder = \OC::$server->get(\OCP\Files\IRootFolder::class)->getUserFolder($uid);
		try {
			$userFolder->get($relPath)->putContent($body);
			http_response_code(200);
		} catch (\OCP\Files\NotFoundException) {
			$dirRel = ltrim(dirname($relPath), '/');
			try { $dir = $userFolder->get($dirRel); }
			catch (\OCP\Files\NotFoundException) { $dir = $userFolder->newFolder($dirRel); }
			$dir->newFile(basename($relPath), $body);
			http_response_code(201);
		}
	} catch (\Throwable $e) {
		error_log('files_picocms write proxy: ' . $e->getMessage());
		http_response_code(500);
	}
	exit;
}

// ── Raw file read (GET ?picocms_raw) ─────────────────────────────────────────
// Returns raw Markdown content for the inline editor.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['picocms_raw']) && $sitePath !== '') {
	if (!$writeGranted
		|| strpos($sitePath, '..') !== false
		|| strpos($sitePath, "\0") !== false
		|| pathinfo($sitePath, PATHINFO_EXTENSION) !== 'md'
	) {
		http_response_code(403);
		exit;
	}
	try {
		$filesRoot  = $dataDir . '/' . $uid . '/files';
		$relPath    = ltrim(substr($contentDir . '/' . $sitePath, strlen($filesRoot)), '/');
		$file       = \OC::$server->get(\OCP\Files\IRootFolder::class)->getUserFolder($uid)->get($relPath);
		header('Content-Type: text/plain; charset=utf-8');
		echo $file->getContent();
	} catch (\Throwable) {
		http_response_code(404);
	}
	exit;
}

// ── Delete proxy (DELETE) ────────────────────────────────────────────────────
// Deletes a page or uploaded media file (same whitelist as the write proxy);
// path traversal rejected; write access required.
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && $sitePath !== '') {
	if (!$writeGranted
		|| strpos($sitePath, '..') !== false
		|| strpos($sitePath, "\0") !== false
		|| !in_array(strtolower(pathinfo($sitePath, PATHINFO_EXTENSION)), $putExtensions, true)
	) {
		http_response_code(403);
		exit;
	}
	try {
		$filesRoot  = $dataDir . '/' . $uid . '/files';
		$relPath    = ltrim(substr($contentDir . '/' . $sitePath, strlen($filesRoot)), '/');
		$userFolder = \OC::$server->get(\OCP\Files\IRootFolder::class)->getUserFolder($uid);
		$userFolder->get($relPath)->delete();
		http_response_code(204);
	} catch (\OCP\Files\NotFoundException) {
		http_response_code(404);
	} catch (\Throwable $e) {
		error_log('files_picocms delete proxy: ' . $e->getMessage());
		http_response_code(500);
	}
	exit;
}

// ── Reject unhandled write methods ───────────────────────────────────────────
// A PUT/DELETE that was not handled above (e.g. empty site path because the
// page carried no file path) must never fall through to Pico — that would
// return a rendered page with HTTP 200 and silently discard the write.
if (in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'DELETE', 'POST'], true)) {
	http_response_code(405);
	header('Allow: GET, HEAD');
	exit;
}

// ── List images (GET ?picocms_list_images) ───────────────────────────────────
// Returns JSON array of image paths relative to the content dir, for the image picker.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['picocms_list_images'])) {
	if (!$writeGranted) {
		http_response_code(403);
		exit;
	}
	$images = [];
	$exts   = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'];
	try {
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($contentDir, FilesystemIterator::SKIP_DOTS)
		);
		foreach ($it as $file) {
			if (!$file->isFile()) {
				continue;
			}
			$ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
			if (!in_array($ext, $exts, true)) {
				continue;
			}
			$images[] = ltrim(substr($file->getPathname(), strlen($contentDir)), '/');
		}
	} catch (\Throwable) {}
	sort($images);
	header('Content-Type: application/json');
	echo json_encode($images);
	exit;
}

// Cross-silo SSO: if the visitor has an NC session on the master (same cookie domain) but no
// session on this silo, redirect them through the sudoConfirm→exchange flow so they get a
// local silo session.  After exchange they land back here with $currentUid set.
// This only fires on silos (not on master itself) to avoid redirect loops.
$isMaster = (bool)$config->getSystemValue('files_sharding_master', false);
if ($currentUid === '' && !$isMaster && ($masterBase !== '') &&
	rtrim($masterBase, '/') !== rtrim($scheme . '://' . $_SERVER['HTTP_HOST'] . $webRoot, '/') &&
	!empty($_COOKIE['nc_username'])
) {
	$siloBase    = rtrim($scheme . '://' . $_SERVER['HTTP_HOST'] . $webRoot, '/');
	$exchangeUrl = $siloBase . '/index.php/apps/files_sharding/login'
		. '?return=' . urlencode($_SERVER['REQUEST_URI']);
	$redirectUrl = rtrim($masterBase, '/') . '/index.php/apps/files_sharding/sudo/confirm'
		. '?silo='     . urlencode($siloBase)
		. '&callback=' . urlencode($exchangeUrl);
	header('Location: ' . $redirectUrl);
	http_response_code(302);
	exit;
}

$pico->loginToEditUrl = ''; // no longer needed; kept for theme compatibility
$pico->setConfig($picoConfig);

try {
	echo $pico->run();
} catch (\Throwable $e) {
	http_response_code(500);
	header('Content-Type: text/html; charset=utf-8');
	$msg = htmlspecialchars($e->getMessage(), ENT_QUOTES);
	$file = htmlspecialchars(str_replace(\OC::$SERVERROOT, '', $e->getFile()), ENT_QUOTES);
	echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>500 — Site Error</title>'
	   . '<style>body{font-family:sans-serif;padding:2em}pre{background:#f4f4f4;padding:1em;overflow:auto;border-radius:4px}</style></head>'
	   . '<body><h1>500 — Site Error</h1>'
	   . '<p><strong>' . $msg . '</strong></p>'
	   . '<p><code>' . $file . ':' . (int)$e->getLine() . '</code></p>'
	   . '</body></html>';
	error_log('files_picocms serve error [' . $siteName . ']: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
	exit;
}

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Resolve a site name via the master's authoritative registry.
 * Only applicable on silos with files_sharding; returns null otherwise.
 */
function _pico_master_lookup(IConfig $config, string $siteName): ?array {
	$isMaster = $config->getSystemValue('files_sharding_master', false);
	if ($isMaster === true || $isMaster === 1 || $isMaster === '1' || $isMaster === 'true') {
		return null;
	}
	if (!class_exists(\OCA\FilesSharding\Service\InterServerClient::class)) {
		return null;
	}
	$url = rtrim((string)$config->getSystemValue('files_sharding_master_internal_url', ''), '/');
	if ($url === '') {
		$url = rtrim((string)$config->getSystemValue('files_sharding_master_url', ''), '/');
	}
	if ($url === '') {
		return null;
	}
	try {
		$client = \OCP\Server::get(\OCA\FilesSharding\Service\InterServerClient::class);
		$data   = $client->getDirect($url, 'internal/lookup', ['site' => $siteName], 'files_picocms');
		return (is_array($data) && !empty($data['site'])) ? $data : null;
	} catch (\Throwable) {
		return null;
	}
}

/**
 * Master-only self-heal: our authoritative registry missed a site. A silo→master
 * registry forward may have been lost during a transient master outage, leaving us
 * stale. Ask every silo we manage; the one that hosts the site answers. Returns the
 * site row with server_url pointing at the responding silo (for the redirect), or
 * null if no silo has it.
 *
 * A short negative cache stops scanner traffic (repeated misses for random names)
 * from fanning out to every silo on each request.
 */
function _pico_silo_broadcast_lookup(IConfig $config, string $siteName): ?array {
	if (!class_exists(\OCA\FilesSharding\Service\ShardingService::class)
		|| !class_exists(\OCA\FilesSharding\Service\InterServerClient::class)) {
		return null;
	}
	$cache = null;
	try {
		$cf    = \OCP\Server::get(\OCP\ICacheFactory::class);
		$cache = $cf->isAvailable() ? $cf->createLocal('files_picocms_heal') : null;
	} catch (\Throwable) {
	}
	if ($cache !== null && $cache->get($siteName)) {
		return null;
	}
	try {
		$sharding = \OCP\Server::get(\OCA\FilesSharding\Service\ShardingService::class);
		if (!$sharding->isMaster()) {
			return null;
		}
		$client = \OCP\Server::get(\OCA\FilesSharding\Service\InterServerClient::class);
		foreach ($sharding->getAllServers() as $server) {
			$apiBase = rtrim($sharding->apiUrlForServer($server), '/');
			if ($apiBase === '') {
				continue;
			}
			$data = $client->getDirect($apiBase, 'internal/lookup', ['site' => $siteName], 'files_picocms');
			if (is_array($data) && !empty($data['site'])) {
				// The silo that answered is the host — redirect there regardless of
				// the user→server assignment table's state on either node.
				$data['server_url'] = rtrim($server->getUrl(), '/');
				return $data;
			}
		}
	} catch (\Throwable $e) {
		error_log('files_picocms heal lookup: ' . $e->getMessage());
	}
	if ($cache !== null) {
		$cache->set($siteName, 1, 60);
	}
	return null;
}

/**
 * Find the effective _config.md for a given request path by walking upward
 * from the most-specific subdirectory to the site root. Returns frontmatter
 * from the first _config.md found (most-specific wins).
 */
function _pico_site_config(string $contentDir, string $sitePath): array {
	// Build candidate directories from most-specific to root
	$dirs = [$contentDir];
	if ($sitePath !== '') {
		$parts = explode('/', ltrim(dirname($sitePath), '.'));
		$cur   = $contentDir;
		foreach ($parts as $part) {
			if ($part === '' || $part === '.') {
				continue;
			}
			$cur    .= '/' . $part;
			$dirs[] = $cur;
		}
		$dirs = array_reverse($dirs); // most-specific first
	}

	foreach ($dirs as $dir) {
		$file = $dir . '/_config.md';
		if (file_exists($file)) {
			return _pico_parse_frontmatter($file);
		}
	}
	return [];
}

/**
 * Parse YAML frontmatter from a Markdown file.
 * Expects content to start with "---\n"; returns [] if not present or on error.
 */
function _pico_parse_frontmatter(string $filePath): array {
	$raw = file_get_contents($filePath);
	if ($raw === false || !str_starts_with($raw, "---\n")) {
		return [];
	}
	$end = strpos($raw, "\n---", 4);
	if ($end === false) {
		return [];
	}
	try {
		$parsed = Yaml::parse(substr($raw, 4, $end - 4));
		return is_array($parsed) ? $parsed : [];
	} catch (\Throwable) {
		return [];
	}
}

/**
 * Emit Cache-Control + Last-Modified + conditional 304 for a static file.
 * Exits with 304 if the client's If-Modified-Since matches.
 */
function _pico_cache_headers(string $filePath, int $maxAge): void {
	$mtime        = filemtime($filePath);
	$lastModified = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';
	header('Cache-Control: public, max-age=' . $maxAge);
	header('Last-Modified: ' . $lastModified);
	$ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
	if ($ifModifiedSince !== '' && strtotime($ifModifiedSince) >= $mtime) {
		http_response_code(304);
		exit;
	}
}

function _pico_mime(string $ext): string {
	return match ($ext) {
		'css'   => 'text/css',
		'js'    => 'application/javascript',
		'svg'   => 'image/svg+xml',
		'png'   => 'image/png',
		'jpg', 'jpeg' => 'image/jpeg',
		'gif'   => 'image/gif',
		'ico'   => 'image/x-icon',
		'html'  => 'text/html',
		'pdf'   => 'application/pdf',
		'woff'  => 'font/woff',
		'woff2' => 'font/woff2',
		'ttf'   => 'font/ttf',
		'eot'   => 'application/vnd.ms-fontobject',
		'nb'    => 'application/vnd.wolfram.mathematica',
		default => 'application/octet-stream',
	};
}
