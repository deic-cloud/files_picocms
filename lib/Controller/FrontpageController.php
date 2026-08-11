<?php

declare(strict_types=1);

namespace OCA\FilesPicoCMS\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;

/**
 * The site-root landing decision, served via index.php.
 *
 * Application::boot() sends a bare "/" here. We deliberately do NOT redirect "/"
 * straight to the PicoCMS landing site (/sites/welcome/): that path is served by
 * remote.php, which enforces the SameSite strict-cookie check — and a visitor
 * arriving right after a cross-site SAML login (WAYF) hasn't sent the strict
 * cookie yet, producing a 412 "Strict Cookie has not been found" error. index.php
 * is exempt from that check, and by the time this controller runs the user session
 * is fully loaded, so the logged-in test here is reliable (unlike in boot()).
 */
class FrontpageController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private IUserSession $userSession,
		private IURLGenerator $urlGenerator,
		private IConfig $config,
	) {
		parent::__construct($appName, $request);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	public function index(): RedirectResponse {
		if ($this->userSession->isLoggedIn()) {
			// Logged in → their default page (Files).
			return new RedirectResponse($this->urlGenerator->linkToDefaultPageUrl());
		}
		// The public landing is MASTER-ONLY. On a silo, send anonymous visitors to the
		// normal login page so direct login works even when the master is down.
		if (!\OCA\FilesPicoCMS\AppInfo\Application::isMasterNode($this->config)) {
			return new RedirectResponse($this->urlGenerator->getAbsoluteURL('/login'));
		}
		// Anonymous → the landing site.
		$site = (string)$this->config->getSystemValue('files_picocms.frontpage_site', 'welcome');
		return new RedirectResponse($this->urlGenerator->getAbsoluteURL('/sites/' . $site . '/'));
	}
}
