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
		// Admin API
		['name' => 'api#getSampleFolder',   'url' => '/api/v1/sample-folder', 'verb' => 'GET'],
		['name' => 'api#setSampleFolder',   'url' => '/api/v1/sample-folder', 'verb' => 'POST'],
	],
	'routes' => [
		// Internal inter-server API (authenticated by shared secret, used by files_sharding)
		['name' => 'internal#listSites',      'url' => '/internal/sites',         'verb' => 'GET'],
		['name' => 'internal#addSite',        'url' => '/internal/sites',         'verb' => 'POST'],
		['name' => 'internal#removeSite',     'url' => '/internal/sites',         'verb' => 'DELETE'],
		['name' => 'internal#lookupSite',     'url' => '/internal/lookup',        'verb' => 'GET'],
		['name' => 'internal#getSampleFolder','url' => '/internal/sample-folder', 'verb' => 'GET'],
		['name' => 'internal#setSampleFolder','url' => '/internal/sample-folder', 'verb' => 'POST'],
		['name' => 'internal#getServePublic', 'url' => '/internal/serve-public',  'verb' => 'GET'],
		['name' => 'internal#setServePublic', 'url' => '/internal/serve-public',  'verb' => 'POST'],
		['name' => 'internal#getUserId',      'url' => '/internal/userid',        'verb' => 'GET'],
	],
];
