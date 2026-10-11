<?php

declare(strict_types=1);

/**
 * A catalogue record's landing page at the record's own address:
 *   /shared/<token>   its public link   (web server: → landing.php?t=<token>)
 *   /doi/<suffix>     its DOI           (web server: → landing.php?doi=<suffix>)
 *
 * Renders the catalogue site's landing view in place (serve.php, catalogue site,
 * SD_LANDING); a /shared/ link that is not in the catalogue is sent on to
 * Nextcloud's share page, as before. Internal rewrites to index.php/remote.php
 * cannot do this — Nextcloud routes by the original REQUEST_URI — so, like
 * files_sharding's legacydav.php, this is a script of its own that boots the
 * stack and then hands over to serve.php.
 */

$sdOrigUri = $_SERVER['REQUEST_URI'] ?? '';
// Core's Request::getRawPathInfo() insists that REQUEST_URI start with the
// script's path; present it that way while the stack boots (cf. legacydav.php).
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

$GLOBALS['SD_LANDING'] = [
	'token' => isset($_GET['t']) ? (string)$_GET['t'] : '',
	'doi'   => isset($_GET['doi']) ? (string)$_GET['doi'] : '',
];
if ($GLOBALS['SD_LANDING']['token'] === '' && $GLOBALS['SD_LANDING']['doi'] === '') {
	http_response_code(404);
	exit;
}
// Render as the catalogue site (first of files_picocms.repository_sites); any
// redirect on the way (e.g. to the node hosting that site) keeps the visitor's address.
$sdCatSites = array_filter(array_map('trim', explode(',', (string)\OCP\Server::get(\OCP\IConfig::class)->getSystemValue('files_picocms.repository_sites', 'public'))));
$_SERVER['SD_LANDING_URI'] = $sdOrigUri;
$_SERVER['REQUEST_URI'] = \OC::$WEBROOT . '/remote.php/sites/' . rawurlencode($sdCatSites !== [] ? reset($sdCatSites) : 'public') . '/';
require __DIR__ . '/serve.php';
