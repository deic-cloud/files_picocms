<?php

declare(strict_types=1);

return [
	'ocs' => [
		// User-facing API
		['name' => 'api#listSites',         'url' => '/api/v1/sites',         'verb' => 'GET'],
		['name' => 'api#addSite',           'url' => '/api/v1/sites',         'verb' => 'POST'],
		['name' => 'api#removeSite',        'url' => '/api/v1/sites',         'verb' => 'DELETE'],
		['name' => 'api#createSite',        'url' => '/api/v1/create',        'verb' => 'POST'],
		['name' => 'api#getHelp',           'url' => '/api/v1/help',          'verb' => 'GET'],
		['name' => 'api#getServePublic',    'url' => '/api/v1/serve-public',  'verb' => 'GET'],
		['name' => 'api#setServePublic',    'url' => '/api/v1/serve-public',  'verb' => 'POST'],
		['name' => 'api#getConfig',         'url' => '/api/v1/config',        'verb' => 'GET'],
		['name' => 'api#putConfig',         'url' => '/api/v1/config',        'verb' => 'POST'],
		// Welcome/terms consent (set the per-user 'welcomed' flag)
		['name' => 'api#setWelcomed',       'url' => '/api/v1/welcomed',      'verb' => 'POST'],
		// Publish-to-catalog: opt a folder in/out of the public catalog
		['name' => 'api#setCatalogListed',  'url' => '/api/v1/catalog',       'verb' => 'POST'],
		// Admin API
		['name' => 'api#getSampleFolder',   'url' => '/api/v1/sample-folder', 'verb' => 'GET'],
		['name' => 'api#setSampleFolder',   'url' => '/api/v1/sample-folder', 'verb' => 'POST'],
		['name' => 'api#setContentUser',    'url' => '/api/v1/content-user',  'verb' => 'POST'],
	],
	'routes' => [
		// App page
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		// Site-root landing decision (Application::boot() sends "/" here so the
		// logged-in vs. anonymous choice is made on index.php, after auth loads —
		// avoids the remote.php strict-cookie 412 on the post-SAML-login "/").
		['name' => 'frontpage#index', 'url' => '/home', 'verb' => 'GET'],
		// Internal inter-server API (authenticated by shared secret, used by files_sharding)
		['name' => 'internal#listSites',      'url' => '/internal/sites',         'verb' => 'GET'],
		['name' => 'internal#addSite',        'url' => '/internal/sites',         'verb' => 'POST'],
		['name' => 'internal#removeSite',     'url' => '/internal/sites',         'verb' => 'DELETE'],
		// POST alias: files_sharding's InterServerClient has no DELETE support
		['name' => 'internal#removeSitePost', 'url' => '/internal/sites/delete',  'verb' => 'POST'],
		['name' => 'internal#lookupSite',     'url' => '/internal/lookup',        'verb' => 'GET'],
		['name' => 'internal#getSampleFolder','url' => '/internal/sample-folder', 'verb' => 'GET'],
		['name' => 'internal#setSampleFolder','url' => '/internal/sample-folder', 'verb' => 'POST'],
		['name' => 'internal#getServePublic', 'url' => '/internal/serve-public',  'verb' => 'GET'],
		['name' => 'internal#setServePublic', 'url' => '/internal/serve-public',  'verb' => 'POST'],
		['name' => 'internal#getUserId',      'url' => '/internal/userid',        'verb' => 'GET'],
	],
];
