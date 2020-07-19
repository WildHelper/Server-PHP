<?php


namespace BjutHelper;


class Cache implements KV
{
	public function get(string $key)
	{
		return false;
	}

	public function set(string $key, $value, $ttl = 600): bool
	{
		return false;
	}

	public function delete(string $key): bool
	{
		return false;
	}
}
