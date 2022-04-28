<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Closure;
use PDO;
use PDOStatement;

class ModelCollection implements Collection, \Iterator, \ArrayAccess, \Countable
{
	// Parameters
	public bool $cache = true;
	public bool $autoExecute = true;
	public bool $autoClose = true;
	public bool $fetchAbsolute = false; // fetch using FETCH_ORI_ABS for lists. Only works when dbDriver supports scrollable cursor.

	public static bool $debugInfo = false;

	// Internal counters
	private int $count = 0;

	private int $index;
	private int|string|null $lastKey;
	private mixed $lastOject;
	private bool $valid;

	// cached data
	private array $keys = [];
	private array $objects = [];

	private array $addedKeys = [];
	private array $modifiedKeys = [];
	private array $removedKeys = [];

	// global cache ?
	// protected static array $collections = [];

	// internal flags/state
	private bool $exhausted = false;
	private bool $reset = false;
	private bool $isList = false;

	public function __construct(
		private ?PDOStatement $statement = null,
		private ?Closure $generator = null,
		private ?int $limit = null, // used to determine if is list
		private int $offset = 0, // used to determine if is list
		private ?PDO $database = null, // used for subsequent queries if any
	)
	{
		$this->init();
	}

	public function __sleep(): array
	{
		$this->cacheAll();

		$array = get_object_vars($this);
		unset($array['statement']);
		unset($array['generator']);
		unset($array['database']);

		return array_keys($array);
	}

	public function __debugInfo(): array
	{
		if(static::$debugInfo)
			return get_object_vars($this);

		return [
			'exhausted' => $this->exhausted,
			'objects' => $this->objects,
		];
	}

	private function init(): void
	{
		$this->count = $this->statement?->rowCount() ?? 0;
		$this->exhausted = false;
	}

	public function reset(bool $keepObjects = false): void
	{
		$this->reset = $this->statement?->closeCursor();
		if(!$keepObjects) {
			$this->keys = [];
			$this->objects = [];
		}
		$this->init();
	}

	public function execute(?array $params = null): bool
	{
		$this->reset = false;

		$result = $this->statement?->execute($params);
		$this->count = $this->statement?->rowCount() ?? 0;

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
		if($this->autoExecute && !empty($this->statement) && (null === $this->statement?->errorCode() || $this->reset)) {
			$this->execute();
		}

		if(!empty($this->statement) || $this->cache)
			[$this->lastKey, $this->lastObject] = $this->fetch($this->index);

		$this->valid = !empty($this->lastObject);
	}

	// fetch from statement or from cache, updates cache. Do not advance cursor.
	private function fetch(int $index): array
	{
		if($this->cache && isset($this->keys[$index])) { // exists in cache
			$key = $this->keys[$index];
			return [$key, $this->objects[$key]];
		} elseif(!empty($this->statement)) { // not yet in cache
			$rs = $this->statement->fetch(
				PDO::FETCH_OBJ,
				PDO::FETCH_ORI_ABS,
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

			if($this->autoClose && isset($this->statement))
				$this->statement->closeCursor();
		}

		return $this->valid;
	}

	public function rewind(): void
	{
		$this->index = 0;
		$this->fetchCurrent();
	}

	public function has(SCRUDInterface $model): bool
	{
		if(enum_exists($model::class)) {
			$this->count = count($this->objects);
			return isset($this[$model->name]);
		} else
			return isset($this[$model->getId()]);
	}

	/*
	 * Implements ArrayAccess
	 */
	public function offsetExists(mixed $key): bool
	{
		return null !== $this[$key];
	}

	public function offsetGet(mixed $key): mixed
	{
		if(isset($this->objects[$key])) {
			return $this->objects[$key];
		}

		if(empty($this->statement))
			return null;

		if($this->autoExecute && (null === $this->statement->errorCode() || $this->reset)) {
			$this->execute();
		}

		if($this->fetchAbsolute && $this->isList && is_int($key)) {
			[, $object] = $this->fetch($key-1);
			return $object;
		} else {
			if(!$this->exhausted) {
				for(
					$i = (($this->isList && is_int($key) && $key>=count($this->keys))? count($this->keys) : $this->offset);
					$i <= (($this->isList && is_int($key)) ? $key : $this->limit ?? $this->count);
					$i++
				) {
					[$k,] = $this->fetch($i);
					if($k == $key)
						break;
				}

				return $this->objects[$key] ?? null;
			}
		}

		return null;
	}

	public function offsetSet(mixed $key, mixed $object): void
	{
		if(isset($key) && isset($this[$key])) { // already in collection
			if($object->hasModifiedProperties() && !in_array($key, $this->modifiedKeys)) { // is modified
				$this->modifiedKeys[] = $key;
			}
			return;
		}

		if(is_null($key)) {
			$this->objects[] = $object;
			$key = array_key_last($this->objects);
		}

		$this->objects[$key] = $object;
		if(!in_array($key, $this->addedKeys))
			$this->addedKeys[] = $key;

		$this->count++;
	}

	public function offsetUnset(mixed $key): void
	{
		if(isset($this->objects[$key])) {
			unset($this->objects[$key]);
			unset($this->keys[array_search($key, $this->keys)]);

			if(in_array($key, $this->addedKeys)) {
				unset($this->addedKeys[$key]);
			} else {
				if(!in_array($key, $this->removedKeys))
					$this->removedKeys[] = $key;
			}
			$this->count--;

			if(in_array($key, $this->modifiedKeys))
				unset($this->modifiedKeys[$key]);

			$this->keys = array_values($this->keys); // re-index
		}
	}

	public function unsetAll(bool $keepObjects = true): int
	{
		if(!$this->exhausted && $this->autoExecute)
			$this->cacheAll();

		$count = count($this->addedKeys) + count($this->modifiedKeys) + count($this->keys);
		$this->removedKeys += $this->addedKeys + $this->modifiedKeys + $this->keys;

		$this->addedKeys = $this->modifiedKeys = $this->keys = [];
		if(!$keepObjects)
			$this->objects = [];

		return $count;
	}

	/*
	 * Get what has changed by ArrayAccesses
	 */
	public function getAddedKeys(): array
	{
		return $this->addedKeys;
	}

	public function getModifiedKeys(): array
	{
		return $this->modifiedKeys;
	}

	public function getRemovedKeys(): array
	{
		return $this->removedKeys;
	}

	public function getModifiedCount(): int
	{
		return count($this->addedKeys) + count($this->modifiedKeys) + count($this->removedKeys);
	}

	/*
	 * Implements Countable
	 */
	public function count(): int
	{
		if($this->autoExecute && !empty($this->statement) && (null === $this->statement->errorCode() || $this->reset)) {
			$this->execute();
		}

		return $this->count;
	}
}
