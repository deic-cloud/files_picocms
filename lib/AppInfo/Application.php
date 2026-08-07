<?php

declare(strict_types=1);

namespace OCA\FilesPicoCMS\AppInfo;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\FilesPicoCMS\Listener\LoadSidebarScriptsListener;
use OCA\FilesPicoCMS\Listener\WelcomeListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	public const APP_ID = 'files_picocms';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadSidebarScriptsListener::class);
		// One-time welcome + terms-consent modal on any logged-in page.
		$context->registerEventListener(BeforeTemplateRenderedEvent::class, WelcomeListener::class);
		// Admin-overview warning if pretty URLs (/sites, /users) aren't rewritten to
		// the app. Guarded: the silo stub IRegistrationContext lacks this method.
		try {
			$context->registerSetupCheck(\OCA\FilesPicoCMS\SetupCheck\PrettyUrlsCheck::class);
		} catch (\Throwable) {
		}
	}

	public function boot(IBootContext $context): void {
		// Serve the landing page at the site root: an anonymous GET of "/" (the
		// index.php front controller) is redirected to the PicoCMS "welcome" site
		// instead of Nextcloud's login page. Logged-in users fall through to their
		// default app (Files). Done here in PHP — not via a web-server rewrite —
		// because routing "/" through remote.php can't resolve the picocms service
		// (the root request's REQUEST_URI stays "/").
		try {
			$server = $context->getServerContainer();
			/** @var \OCP\IRequest $request */
			$request = $server->get(\OCP\IRequest::class);
			/** @var \OCP\IUserSession $userSession */
			$userSession = $server->get(\OCP\IUserSession::class);

			if (\OC::$CLI) {
				return;
			}
			// Only the main web front controller — never remote.php / ocs / status.
			$script = $_SERVER['SCRIPT_NAME'] ?? '';
			if (substr($script, -10) !== '/index.php') {
				return;
			}
			if ($request->getMethod() !== 'GET') {
				return;
			}
			$pathInfo = $request->getPathInfo();
			if ($pathInfo !== '' && $pathInfo !== '/') {
				return;
			}
			if ($userSession->isLoggedIn()) {
				return;
			}

			// Hand off to the FrontpageController (index.php) rather than jumping
			// straight to the /sites/ landing (remote.php): the controller re-checks
			// login AFTER auth is loaded and, being on index.php, is exempt from the
			// strict-cookie 412 that bit a just-logged-in cross-site "/" request.
			// (isLoggedIn() here in boot() can be false for such a request because
			// the SameSite strict cookie isn't sent on the WAYF cross-site return.)
			/** @var \OCP\IURLGenerator $urlGenerator */
			$urlGenerator = $server->get(\OCP\IURLGenerator::class);
			header('Location: ' . $urlGenerator->linkToRouteAbsolute('files_picocms.frontpage.index'));
			exit;
		} catch (\Throwable) {
			// Never let the landing redirect break normal request handling.
			return;
		}
	}
}
