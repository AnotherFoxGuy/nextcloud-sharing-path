<?php

declare(strict_types=1);

if (!defined('PHPUNIT_RUN')) {
	define('PHPUNIT_RUN', 1);
}

require_once __DIR__ . '/../../../lib/base.php';

// Fix for "Autoload path not allowed: .../tests/lib/testcase.php"
\OC::$loader->addValidRoot(OC::$SERVERROOT . '/tests');

// Fix for "Autoload path not allowed: .../sharingpath/tests/testcase.php"
\OC_App::loadApp('sharingpath');

if (!class_exists('PHPUnit_Framework_TestCase')) {
	require_once(__DIR__ . '/PHPUnit/Autoload.php');
}

OC_Hook::clear();
