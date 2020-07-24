<?php


namespace WildHelper;


class Cache implements KV
{
	public function get(string $key)
	{
		return apcu_fetch($key);
	}

	public function set(string $key, $value, $ttl = 600): bool
	{
		return apcu_store($key, $value, $ttl);
	}

	public function delete(string $key): bool
	{
		return apcu_delete($key);
	}
}
