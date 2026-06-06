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
 *   edit_links: yes | no
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

// ── Determine master login base URL ──────────────────────────────────────────

$masterBase = rtrim((string)$config->getSystemValue('files_sharding_master_url', ''), '/');
if ($masterBase === '') {
	$scheme     = \OC::$server->get(\OCP\IRequest::class)->getServerProtocol();
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
	// Site lives in a group grant folder
	$siteFsPath = $dataDir . '/' . $uid . '/user_group_admin/' . $gid . $siteInfo['path'];
} else {
	$siteFsPath = $dataDir . '/' . $uid . '/files' . $siteInfo['path'];
}

if (!is_dir($siteFsPath)) {
	http_response_code(404);
	exit;
}

// ── Override NC's restrictive CSP with one suitable for website serving ───────
header('Content-Security-Policy: default-src \'self\'; style-src \'self\' \'unsafe-inline\' https://fonts.googleapis.com; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\'; img-src \'self\' data: blob:; font-src \'self\' data: https://fonts.gstatic.com; connect-src \'self\'; frame-ancestors \'self\'');

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

// Serve image/binary/font files directly
$rawExtensions = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'pdf', 'nb'];
if ($sitePath !== '' && in_array($extension, $rawExtensions, true)) {
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
	'base_uri'          => $webRoot . '/remote.php/files_picocms/' . ($siteName !== null ? 'sites/' . urlencode($siteName) : 'users/' . urlencode($userEmail)),
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
if (isset($siteConfig['edit_links'])) {
	$picoConfig['edit_links'] = (strtolower((string)$siteConfig['edit_links']) === 'yes');
}
if (!empty($siteConfig['favicon'])) {
	$picoConfig['favicon'] = $siteConfig['favicon'];
}

// Login URL for themes (e.g. "log in" link on 403 pages)
$picoConfig['login_url']      = $masterBase . '/index.php/login?redirect_url=' . urlencode($_SERVER['REQUEST_URI']);
$picoConfig['original_path']  = $sitePath;

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
