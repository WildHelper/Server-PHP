<?php


namespace WildHelper;


class Cache implements KV
{
	private \Memcached $cache;

	public function __construct()
	{
		if (class_exists('\Memcached')) {
			$this->cache = new \Memcached();
		}
	}

	public function get(string $key)
	{
		return $this->cache->get($key);
	}

	public function set(string $key, $value, $ttl = 600): bool
	{
		return $this->cache->set($key, $value, time() + $ttl);
	}

	public function delete(string $key): bool
	{
		return $this->cache->delete($key);
	}
}
