<?php

declare(strict_types=1);

namespace Reflexive\Model;

class Collection implements \Iterator, \ArrayAccess, \Countable
{
	// Parameters
	public bool $cache = true;
	public bool $autoExecute = true;
	public bool $autoClose = true;
	public bool $fetchAbsolute = false; // fetch using FETCH_ORI_ABS for lists. Only works when dbsDriver supports scrollable cursor.

	// Internal counters
	private int $count = 0;

	private int $index;
	private int|string|null $lastKey;
	// private mixed $lastOject;
	private bool $valid;

	// cached data
	private array $keys = [];
	private array $objects = [];

	// global cache ?
	protected static array $collections = [];

	// internal flags/state
	private bool $exhausted = false;
	private bool $reset = false;
	private bool $isList = false;

	public function __construct(
		private \PDOStatement $statement,
		private \Closure $generator,
		private ?int $limit = null, // used to determine if is list
		private int $offset = 0, // used to determine if is list
		private ?\PDO $database = null, // used for subsequent queries if any
	)
	{
		$this->init();
	}

	public function __sleep(): array
	{
		$this->cacheAll();

		return [
			'cache',
			'autoExecute',
			'autoClose',
			'fetchAbsolute',
			'count',
			'index',
			'lastKey',
			'valid',
			'keys',
			'objects',
			'exhausted',
			'reset',
			'isList',
			'limit',
			'offset',
		];
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

		$this->isList = (0 === $this->offset && $this->limit == $this->count);

		return $result;
	}

	/*
	 * Implements Iterator
	 */
	public function current(): mixed
	{
		return $this->lastObject;
	}

	private function fetchCurrent(): void
	{
		if($this->autoExecute && (null === $this->statement->errorCode() || $this->reset)) {
			$this->execute();
		}

		[$this->lastKey, $this->lastObject] = $this->fetch($this->index);

		$this->valid = !empty($this->lastObject);
	}

	// fetch from statement or from cache, updates cache. Do not advance cursor.
	private function fetch(int $index): array
	{
		if($this->cache && isset($this->keys[$index])) { // exists in cache
			$key = $this->keys[$index];
			return [$key, $this->objects[$key]];
		} else { // not yet in cache
			$rs = $this->statement->fetch(
				\PDO::FETCH_OBJ,
				\PDO::FETCH_ORI_ABS,
				$index
			);

			if(empty($rs)) {
				return [null, null];
			} else {
				[$key, $object] = ($this->generator)($rs, $this->database);

				if($this->cache) {
					$this->keys[$index] = $key;
					$this->objects[$key] = $object;
				}

				return [$key, $object];
			}
		}

		return [null, null];
	}

	private function cacheAll(): void
	{
		if($this->cache && $this->exhausted)
			return;
		else {
			$this->cache = true;
			foreach($this as $k => $v) {
				$k;
				$v;
			}
		}
	}

	public function next(): void
	{
		$this->index++;
		$this->fetchCurrent();
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
		$this->fetchCurrent();
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

		if($this->autoExecute && (null === $this->statement->errorCode() || $this->reset)) {
			$this->execute();
		}

		if($this->fetchAbsolute && $this->isList) {
			[, $object] = $this->fetch($key-1);
			return $object;
		} else {
			if(!$this->exhausted) {
				for(
					$i = (($this->isList && $key>=count($this->keys))? count($this->keys) : $this->offset);
					$i <= ($this->isList ? $key : $this->limit ?? $this->count);
					$i++
				) {
					[$k,] = $this->fetch($i);
					if($k == $key)
						break;
				}

				return $this->objects[$key];
			}
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
		if($this->autoExecute && (null === $this->statement->errorCode() || $this->reset)) {
			$this->execute();
		}

		return $this->count;
	}
}
