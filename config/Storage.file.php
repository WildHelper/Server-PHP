<?php


namespace WildHelper;


/**
 * Class Storage
 * @package BjutHelper
 *
 * 自行实现一个存储类
 * 这里是使用文件存储实现
 * 实际生产环境中建议使用 NoSQL
 */
class Storage implements KV
{
	public function get(string $key)
	{
		return unserialize(@file_get_contents($key));
	}

	public function set(string $key, $value): bool
	{
		$paths = explode('/', $key);
		$count = count($paths);
		if ($count > 1) {
			unset($paths[$count-1]);
			$dir = implode('/', $paths);
			if (!is_dir($dir)) {
				mkdir($dir, 0755, true);
			}
			echo $dir;
		}
		return file_put_contents($key, serialize($value));
	}

	public function delete(string $key): bool
	{
		return @unlink($key);
	}
}
