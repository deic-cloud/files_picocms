<?php

/**
 * Load all 3rdparty libraries needed for Pico CMS rendering.
 * Called once from appinfo/serve.php.
 */

// Twig v1 — use its own autoloader
require_once __DIR__ . '/Twig/lib/Twig/Autoloader.php';
Twig_Autoloader::register();

// Symfony YAML (needed by Pico for page front-matter parsing)
require_once __DIR__ . '/Yaml/Exception/ExceptionInterface.php';
require_once __DIR__ . '/Yaml/Exception/RuntimeException.php';
require_once __DIR__ . '/Yaml/Exception/ParseException.php';
require_once __DIR__ . '/Yaml/Exception/DumpException.php';
require_once __DIR__ . '/Yaml/Unescaper.php';
require_once __DIR__ . '/Yaml/Escaper.php';
require_once __DIR__ . '/Yaml/Inline.php';
require_once __DIR__ . '/Yaml/Parser.php';
require_once __DIR__ . '/Yaml/Dumper.php';
require_once __DIR__ . '/Yaml/Yaml.php';

// Parsedown (Markdown parser used by Pico)
require_once __DIR__ . '/Parsedown.php';
require_once __DIR__ . '/ParsedownExtra.php';

// Pico CMS core (our fork)
require_once __DIR__ . '/PicoPluginInterface.php';
require_once __DIR__ . '/AbstractPicoPlugin.php';
require_once __DIR__ . '/PicoTwigExtension.php';
require_once __DIR__ . '/Pico.php';
