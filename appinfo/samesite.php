<?php

declare(strict_types=1);

/**
 * Included by the app's own entry scripts (landing.php, sites.php) BEFORE they
 * boot Nextcloud.
 *
 * A browser that holds this server's cookies (logged in, or visited before) and
 * follows a link from another site - an e-mail, a search result - does not send
 * Nextcloud's SameSite=Strict marker cookie, and core's base.php then refuses
 * every script but index.php/public.php/cron.php with 412 "Strict Cookie has not
 * been found in request". Such a page view (GET/HEAD, cookies present, strict
 * marker absent) is answered here with a tiny page that reloads the same address:
 * the reload starts on this site, so the browser sends all cookies. One extra
 * round trip, only in that case, and Nextcloud is not booted for it.
 *
 * A 30 s guard cookie stops a second reload if the strict cookie still does not
 * arrive (cookies blocked), so a browser can never loop; it is cleared once the
 * strict cookie is there. Requests that change something (POST etc.) are left to
 * core's check.
 */

(static function (): void {
	$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
	$strict = isset($_COOKIE['__Host-nc_sameSiteCookiestrict']) || isset($_COOKIE['nc_sameSiteCookiestrict']);
	$guard = 'files_picocms_samesite_reload';
	$opts = ['path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax'];
	if (($method === 'GET' || $method === 'HEAD') && $_COOKIE !== [] && !$strict && !isset($_COOKIE[$guard])) {
		setcookie($guard, '1', ['expires' => time() + 30] + $opts);
		header('Content-Type: text/html; charset=utf-8');
		header('Cache-Control: no-store');
		header('Referrer-Policy: no-referrer');
		echo '<!doctype html><html><head><meta charset="utf-8"><title>Loading…</title>'
			. '<noscript><meta http-equiv="refresh" content="0"></noscript>'
			. '<script>location.replace(location.href)</script></head><body></body></html>';
		exit;
	}
	if ($strict && isset($_COOKIE[$guard])) {
		setcookie($guard, '', ['expires' => 1] + $opts);
	}
})();
