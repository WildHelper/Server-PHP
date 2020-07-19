<?php


namespace BjutHelper;


interface KV {
	public function get(string $key);
	public function set(string $key, $value): bool;
	public function delete(string $key): bool;
}
