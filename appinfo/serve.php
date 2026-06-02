<?php

/**
 * Pico CMS site rendering endpoint.
 *
 * URL scheme (relative to NC webroot):
 *   remote.php/files_picocms/sites/{name}[/{path}]  → named site
 *   remote.php/files_picocms/users/{email}[/{path}]  → user public page
 *
 * This file is registered as <files_picocms>appinfo/serve.php</files_picocms>
 * in info.xml. NC's remote.php loads the NC bootstrap before executing it,
 * so \OC::$server is available.
 */

declare(strict_types=1);

use OCA\FilesPicoCMS\Db\SiteMapper;
use OCA\FilesPicoCMS\Service\SiteService;
use OCP\IConfig;

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

// $uriPath is now e.g. /remote.php/files_picocms/sites/mysite/themes/x.css
$prefix    = '/remote.php/files_picocms';
$localPath = str_starts_with($uriPath, $prefix) ? substr($uriPath, strlen($prefix)) : $uriPath;
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

// ── Resolve site info ─────────────────────────────────────────────────────────

/** @var SiteService $siteService */
$siteService = \OC::$server->get(SiteService::class);
/** @var IConfig $config */
$config = \OC::$server->get(IConfig::class);

$siteInfo = null;

if ($userEmail !== null) {
	$uid = $siteService->getUserIdFromEmail($userEmail);
	if ($uid === null) {
		http_response_code(404);
		exit;
	}
	if (!$siteService->getServePublicUrl($uid)) {
		http_response_code(403);
		exit;
	}
	$siteInfo = [
		'uid'  => $uid,
		'path' => '/public',
		'site' => 'Public page',
		'gid'  => '',
	];
	$baseUrl = 'https://' . $_SERVER['HTTP_HOST'] . $webRoot . '/remote.php/files_picocms/users/' . urlencode($userEmail);
} else {
	$siteInfo = $siteService->lookupSite($siteName);
	if ($siteInfo === null) {
		http_response_code(404);
		exit;
	}
	$baseUrl = 'https://' . $_SERVER['HTTP_HOST'] . $webRoot . '/remote.php/files_picocms/sites/' . urlencode($siteName);
}

$uid = $siteInfo['uid'];

// ── files_sharding: redirect to correct silo if needed ───────────────────────

try {
	if (
		\OCP\Server::get(\OCP\IAppManager::class)->isInstalled('files_sharding') &&
		!\OCA\FilesSharding\Lib::onServerForUser($uid)
	) {
		$userServerUrl = \OCA\FilesSharding\Lib::getServerForUser($uid);
		if (!empty($userServerUrl)) {
			header('Location: ' . $userServerUrl . $_SERVER['REQUEST_URI']);
			http_response_code(307);
			exit;
		}
	}
} catch (\Throwable) {
	// files_sharding not available or error — serve locally
}

// ── Build filesystem paths ────────────────────────────────────────────────────

$dataDir = $config->getSystemValue('datadirectory', '');
if ($dataDir === '') {
	http_response_code(500);
	exit;
}

$gid = $siteInfo['gid'] ?? '';
if ($gid !== '') {
	// Site lives in a group grant folder
	$siteFsPath = $dataDir . '/' . $uid . '/user_group_admin/' . $gid . $siteInfo['path'];
} else {
	$siteFsPath = $dataDir . '/' . $uid . '/files' . $siteInfo['path'];
}

if (!is_dir($siteFsPath)) {
	http_response_code(404);
	exit;
}

// ── Determine themes and content directories ──────────────────────────────────

$appDir    = dirname(__DIR__);
$hasThemes = is_dir($siteFsPath . '/themes');

if ($hasThemes) {
	$themesDir   = $siteFsPath . '/themes/';
} else {
	$themesDir   = $appDir . '/themes/';
}

if (is_dir($siteFsPath . '/content')) {
	$contentDir = $siteFsPath . '/content';
} else {
	$contentDir = $siteFsPath;
}

// ── Handle raw asset requests (themes, images, CSS, JS, etc.) ────────────────

$extension = $sitePath !== '' ? strtolower(pathinfo($sitePath, PATHINFO_EXTENSION)) : '';

// Serve theme files directly from the themes directory
if (str_starts_with($sitePath, 'themes/')) {
	$themeFile = $siteFsPath . '/' . $sitePath;
	if (!file_exists($themeFile)) {
		$themeFile = $appDir . '/' . $sitePath;
	}
	if (file_exists($themeFile)) {
		header('Content-Type: ' . _pico_mime($extension));
		_pico_cache_headers($themeFile, 86400);
		readfile($themeFile);
	} else {
		http_response_code(404);
	}
	exit;
}

// Serve image/binary/font files directly
$rawExtensions = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'pdf', 'nb'];
if ($sitePath !== '' && in_array($extension, $rawExtensions, true)) {
	$filePath = $siteFsPath . '/' . $sitePath;
	if (!file_exists($filePath)) {
		$filePath = $contentDir . '/' . $sitePath;
	}
	if (file_exists($filePath)) {
		header('Content-Type: ' . _pico_mime($extension));
		// Long cache for fonts/icons; shorter for user images (may be updated)
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

if (in_array($extension, ['html', 'css', 'js'], true) && $extension !== 'md') {
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

// Tell Pico which sub-page to render
if ($sitePath !== '') {
	$_SERVER['QUERY_STRING'] = $sitePath;
}

$picoConfig = [
	'base_url'          => $baseUrl,
	'base_uri'          => $webRoot . '/remote.php/files_picocms/' . ($siteName !== null ? 'sites/' . urlencode($siteName) : 'users/' . urlencode($userEmail)),
	'content_dir'       => $contentDir,
	'rewrite_url'       => true,
	'site_title'        => $siteInfo['site'],
	'user'              => $uid,
	'group'             => $gid,
	'pages_order_by'    => 'date',
	'pagination'        => -1,
	'pagination_limit'  => 10,
	'toc_top_txt'       => '',
];

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

$pico->setConfig($picoConfig);
$pico->ocOwner = $uid;

echo $pico->run();

// ── Helper ────────────────────────────────────────────────────────────────────

/**
 * Emit Cache-Control + Last-Modified + conditional 304 for a static file.
 * Exits with 304 if the client's If-Modified-Since matches.
 */
function _pico_cache_headers(string $filePath, int $maxAge): void {
	$mtime = filemtime($filePath);
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
		'css'  => 'text/css',
		'js'   => 'application/javascript',
		'svg'  => 'image/svg+xml',
		'png'  => 'image/png',
		'jpg', 'jpeg' => 'image/jpeg',
		'gif'  => 'image/gif',
		'ico'  => 'image/x-icon',
		'html' => 'text/html',
		'pdf'  => 'application/pdf',
		'woff' => 'font/woff',
		'woff2'=> 'font/woff2',
		'ttf'  => 'font/ttf',
		'eot'  => 'application/vnd.ms-fontobject',
		'nb'   => 'application/vnd.wolfram.mathematica',
		default => 'application/octet-stream',
	};
}
