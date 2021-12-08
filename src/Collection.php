<?php

declare(strict_types=1);

namespace Reflexive\Core;

class Collection implements \Iterator, \ArrayAccess, \Countable
{
	// Parameters
	public bool $cache = true;
	public bool $autoExecute = true;
	public bool $autoClose = true;

	// Internal counters
	private int $count = 0;

	private int $index;
	private int|string|null $lastKey;
	private mixed $lastOject;
	private bool $valid;

	// cached data
	private array $keys = [];
	private array $objects = [];

	// internal flags/state
	private bool $exhausted = false;
	private bool $reset = false;

	public function __construct(
		private \PDOStatement $statement,
		private \Closure $generator,
	)
	{
		$this->init();
	}

	private function init(): void
	{
		$this->count = $this->statement->rowCount();
		$this->exhausted = false;
	}

	public function reset(bool $keepObjects = false): void
	{
		$this->reset = $this->statement->closeCursor();
		if(!$keepObjects) {
			$this->keys = [];
			$this->objects = [];
		}
		$this->init();
	}

	public function execute(?array $params = null): bool
	{
		$this->reset = false;

		$result = $this->statement->execute($params);
		$this->count = $this->statement->rowCount();

		return $result;
	}

	/*
	 * Implements Iterator
	 */
	public function current(): mixed
	{
		return $this->lastObject;
	}

	private function fetch(): void
	{
		if($this->autoExecute && (null === $this->statement->errorCode() || $this->reset)) {
			$this->execute();
		}

		if($this->cache && isset($this->keys[$this->index])) {
			$this->lastKey = $this->keys[$this->index];
			$this->lastObject = $this->objects[$this->lastKey];
		} else {
			$rs = $this->statement->fetch(
				\PDO::FETCH_OBJ,
				\PDO::FETCH_ORI_ABS,
				$this->index
			);

			if(!empty($rs)) {
				[$this->lastKey, $this->lastObject] = ($this->generator)($rs);
			} else {
				$this->lastKey = null;
				$this->lastObject = null;
			}

			if($this->cache) {
				$this->keys[$this->index] = $this->lastKey;
				$this->objects[$this->lastKey] = $this->lastObject;
			}
		}

		$this->valid = !empty($this->lastObject);
	}

	public function next(): void
	{
		$this->index++;
		$this->fetch();
	}

	public function key(): mixed
	{
		return $this->lastKey;
	}

	public function valid(): bool
	{
		if(!$this->valid) {
			$this->exhausted = true;

			if($this->autoClose)
				$this->statement->closeCursor();
		}

		return $this->valid;
	}

	public function rewind(): void
	{
		$this->index = 0;
		$this->fetch();
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

		if(!$this->exhausted) {
			foreach($this as $v) {}

			return $this->offsetGet($key);
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
		return $this->count;
	}
}
