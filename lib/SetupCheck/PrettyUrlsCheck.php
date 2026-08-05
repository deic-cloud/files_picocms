<?php

declare(strict_types=1);

namespace OCA\FilesPicoCMS\SetupCheck;

use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;

/**
 * Admin-overview check: are the Websites pretty URLs (/sites, /users) actually
 * rewritten to the app? Rather than parse a specific web server's config (not
 * portable), it probes the real HTTP behaviour — GET /sites/__picocms_probe and
 * look for the marker that appinfo/serve.php returns. This is how Nextcloud core
 * detects mod_rewrite / .well-known issues, so it works on any web server.
 *
 * Only meaningful when pretty URLs are in use (files_picocms.url_prefix empty).
 */
class PrettyUrlsCheck implements ISetupCheck {
	public function __construct(
		private IConfig        $config,
		private IClientService $clientService,
		private IURLGenerator  $urlGenerator,
	) {}

	public function getCategory(): string {
		return 'system';
	}

	public function getName(): string {
		return 'Websites (files_picocms) pretty URLs';
	}

	public function run(): SetupResult {
		// Non-pretty mode (url_prefix set, e.g. "/remote.php") needs no rewrite.
		$prefix = trim((string)$this->config->getSystemValue('files_picocms.url_prefix', '/remote.php/files_picocms'));
		if ($prefix !== '') {
			return SetupResult::success('Pretty URLs are disabled (files_picocms.url_prefix is set); no web server rewrite required.');
		}

		$url = $this->urlGenerator->getAbsoluteURL('/sites/__picocms_probe');
		try {
			$response = $this->clientService->newClient()->get($url, [
				'http_errors' => false,
				'verify'      => false,
				'timeout'     => 10,
				'nextcloud'   => ['allow_local_address' => true],
			]);
		} catch (\Throwable $e) {
			// Network/TLS problem reaching ourselves — can't conclude either way.
			return SetupResult::info('Could not verify the Websites pretty-URL rewrite (probe request failed: ' . $e->getMessage() . ').');
		}

		if ($response->getStatusCode() === 200 && trim($response->getHeader('X-Picocms-Probe')) === 'ok') {
			return SetupResult::success('Websites pretty URLs (/sites, /users) resolve correctly.');
		}

		return SetupResult::warning(
			'Websites pretty URLs are not resolving — a request to /sites/… does not reach the app, '
			. 'so published sites load unstyled (their CSS/JS 404). Add a web server rewrite from '
			. '/sites and /users to /remote.php/sites and /remote.php/users '
			. '(Apache: RewriteRule ^ /remote.php%{REQUEST_URI} for those paths; Caddy: rewrite to /remote.php{uri}), '
			. 'or set the "files_picocms.url_prefix" system config to "/remote.php" to serve non-pretty URLs instead.'
		);
	}
}
