<?php

declare(strict_types=1);

/**
 * Entry script for the published sites, in front of serve.php - what remote.php
 * does for /remote.php/sites/... and /remote.php/users/..., plus samesite.php's
 * reload for links followed from another site. Without it a reader who holds
 * this server's cookies (e.g. a collaborator who is logged in) and opens a site
 * link from an e-mail gets 412 from core's strict-cookie check instead of the page.
 *
 * The web server rewrites the site addresses here (internal rewrite):
 *   /sites/..., /users/..., /remote.php/sites/..., /remote.php/users/...
 *       -> sites.php                         (the original address is served)
 *   any other address, e.g. /terms
 *       -> sites.php/remote.php/sites/terms  (the path after sites.php is served)
 * The second form also works called directly, which is how it is tested where
 * the web server has no such rewrites.
 */

require __DIR__ . '/samesite.php';

$sdOrigUri = $_SERVER['REQUEST_URI'] ?? '';
$sdPath = strtok($sdOrigUri, '?') ?: '';
$sdQuery = (string)parse_url($sdOrigUri, PHP_URL_QUERY);
$sdTarget = null;
if (!str_contains($sdPath, '/appinfo/sites.php')
	&& preg_match('#/(?:remote\.php/(?:files_picocms/)?)?(?:sites|users)/#', $sdPath) === 1) {
	$sdTarget = $sdOrigUri;
} else {
	$sdInfo = (string)($_SERVER['PATH_INFO'] ?? '');
	if (preg_match('#^/(?:remote\.php/(?:files_picocms/)?)?(?:sites|users)/#', $sdInfo) === 1) {
		// some web servers drop a trailing slash from PATH_INFO; keep the address's
		$sdTrail = str_ends_with($sdPath, '/') && !str_ends_with($sdInfo, '/') ? '/' : '';
		$sdTarget = implode('/', array_map('rawurlencode', explode('/', $sdInfo))) . $sdTrail
			. ($sdQuery !== '' ? '?' . $sdQuery : '');
	}
}
if ($sdTarget === null) {
	http_response_code(404);
	exit;
}

// Core's Request::getRawPathInfo() insists that REQUEST_URI start with the
// script's path; present it that way while the stack boots (cf. landing.php).
if (($_SERVER['SCRIPT_NAME'] ?? '') !== '' && !str_starts_with($sdOrigUri, $_SERVER['SCRIPT_NAME'])) {
	$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'] . $sdOrigUri;
}
require_once __DIR__ . '/../../../lib/base.php';
if (\OCP\Util::needUpgrade()) {
	http_response_code(503);
	exit('Service unavailable (upgrade in progress)');
}
\OC::$REQUESTEDAPP = 'files_picocms';
$sdAppManager = \OCP\Server::get(\OCP\App\IAppManager::class);
$sdAppManager->loadApps(['authentication', 'extended_authentication', 'filesystem', 'logging']);
$sdAppManager->loadApp('files_picocms');

// serve.php reads the site address from REQUEST_URI (webroot-relative paths
// are fine: it strips the webroot itself).
$_SERVER['REQUEST_URI'] = str_starts_with($sdTarget, '/') && \OC::$WEBROOT !== '' && !str_starts_with($sdTarget, \OC::$WEBROOT . '/')
	? \OC::$WEBROOT . $sdTarget : $sdTarget;
require __DIR__ . '/serve.php';
