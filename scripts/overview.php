<?php
require_once __DIR__ . '../config/Settings.php';

use BjutHelper\Settings;

if (
	(isset($argv[1]) && $argv[1] === Settings::CLOUD_FUNCTION_AUTH) ||
	(isset($_GET['auth']) && $_GET['auth'] === Settings::CLOUD_FUNCTION_AUTH)
) {
	require_once __DIR__ . '../src/Utils.php';
	require_once __DIR__ . '../src/KV.php';
	require_once __DIR__ . '../src/Data.php';
	require_once __DIR__ . '../config/Storage.php';
	require_once __DIR__ . '../src/Analyze.php';
	require_once __DIR__ . '../config/Settings.php';
	new BjutHelper\Analyze();
}
