<?php

require_once __DIR__ . '../src/Utils.php';
require_once __DIR__ . '../src/KV.php';
require_once __DIR__ . '../src/Data.php';
require_once __DIR__ . '../src/WeChat.php';
require_once __DIR__ . '../src/Analyze.php';
require_once __DIR__ . '../config/Storage.php';
require_once __DIR__ . '../config/Cache.php';
require_once __DIR__ . '../config/Settings.php';

use WildHelper\Settings;
use WildHelper\Storage;
use WildHelper\WeChat;


if (
	(isset($argv[1]) && $argv[1] === Settings::CLOUD_FUNCTION_AUTH) ||
	(isset($_GET['auth']) && $_GET['auth'] === Settings::CLOUD_FUNCTION_AUTH)
) {
	$time_start = microtime(true);
	$storage = new Storage();
	$newCourses = $storage->pkrget('/opt/wild/subscribe/started', 1);
	foreach ($newCourses as $key => $_) {
		try {
			$success = 0;
			$rejected = 0;
			$failed = 0;
			$arr = explode('/', $key);
			$courseId = $arr[count($arr) - 1];
			$term = $arr[count($arr) - 2];
			$year = $arr[count($arr) - 3];
			$storage->set('/opt/wild/subscribe/finished/'.$year.'/'.$term.'/'.$courseId, 1);
			$storage->delete('/opt/wild/subscribe/started/'.$year.'/'.$term.'/'.$courseId);
			$newCourse = $storage->get('/opt/wild/subscribe/all/'.$year.'/'.$term.'/'.$courseId);
			if (is_object($newCourse)) {
				$name = $newCourse->course->name;
				$id = $newCourse->course->id;
				$users = $newCourse->users;
				$instructor = $newCourse->course->instructor;
				foreach ($users as $_ => $open) {
					try {
						$ret = WeChat::sendScore($open, $id, $name, $year, $term);
					} catch (Exception $e) {}
				}
				echo "success";
			}
		} catch (Exception $exception) {}
	}
	$time_end = microtime(true);
	$time = $time_end - $time_start;

	echo "Did something in $time seconds\n";
}
