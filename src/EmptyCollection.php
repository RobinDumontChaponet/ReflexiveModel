<?php

declare(strict_types=1);

namespace Reflexive\Model;

use ArrayIterator;

class EmptyCollection extends ArrayIterator implements Collection, \ArrayAccess, \Countable
{
	// Parameters
	public readonly bool $cache;

	// cached data
	private array $keys = [];
	private array $objects = [];

	// internal flags/state
	private bool $reset = false;

	// global cache ?
	protected static array $collections = [];

	public function __construct(
	)
	{
		$this->cache = true;
		$this->init();
	}

	public function __sleep(): array
	{
		return [
			'cache',
			'keys',
			'objects',
		];
	}

	private function init(): void
	{
	}

	public function reset(bool $keepObjects = false): void
	{
		$this->reset = true;
		if(!$keepObjects) {
			$this->keys = [];
			$this->objects = [];
		}
		$this->init();
	}

	public function execute(?array $params = null): bool
	{
		$this->reset = false;

		return false;
	}

	/*
	 * Implements ArrayAccess
	 */
	public function offsetExists(mixed $key): bool
	{
		return isset($this->objects[$key]);
	}

	public function offsetGet(mixed $key): mixed
	{
		if(isset($this->objects[$key])) {
			return $this->objects[$key];
		}

		return null;
	}

	public function offsetSet(mixed $key, mixed $object): void
	{
		if(is_null($key)) {
			$this->objects[] = $object;
			$this->keys[] = array_key_last($this->objects);
		} else {
			$this->objects[$key] = $object;

			if(!in_array($key, $this->keys))
				$this->keys[] = $key;
		}
	}

	public function offsetUnset(mixed $key): void
	{
		unset($this->objects[$key]);
		unset($this->keys[array_search($key, $this->keys)]);

		$this->keys = array_values($this->keys); // re-index
	}

	/*
	 * Implements Countable
	 */
	public function count(): int
	{
		return count($this->objects);
	}
}
