<?php

declare(strict_types=1);

namespace Reflexive\Model;

use InvalidArgumentException;
use PDO;
use PDOStatement;

/**
 * @implements \Iterator<int|string|null, mixed>
 * @implements \ArrayAccess<int|string|null, mixed>
 */
class ModelCollection implements Collection, \Iterator, \ArrayAccess, \Countable, \JsonSerializable
{
	// Parameters
	public bool $cache = true;
	public bool $autoExecute = true;
	public bool $autoClose = true;
	public bool $fetchAbsolute = false; // fetch using FETCH_ORI_ABS for lists. Only works when dbDriver supports scrollable cursor.

	public static bool $debugInfo = false;

	// Internal counters
	private ?int $count = null;

	private int $index;
	private int|string|null $lastKey;
	private mixed $lastObject;
	private bool $valid;

	// cached data
	private array $keys = [];
	private array $objects = [];

	private array $addedKeys = [];
	private array $modifiedKeys = [];
	private array $removedKeys = [];
	private array $removedObjects = [];

	// global cache ?
	// protected static array $collections = [];

	// internal flags/state
	private bool $exhausted = false;
	private bool $reset = false;
	private bool $isList = false;

	public function __construct(
		private string $className,
		private PDOStatement|\Reflexive\Query\Composed|null $statement = null,
		private ?Hydrator $hydrator = null,
		private ?int $limit = null, // used to determine if is list
		private int $offset = 0, // used to determine if is list
		private ?PDO $database = null, // used for subsequent queries if any
	)
	{
		$this->init();

		if($statement instanceof \Reflexive\Query\Composed) {
			if(null === $database)
			throw new InvalidArgumentException('Statement is Reflexive\Query but no database given to prepare it.');

			$this->reset = true;
		}
	}

	public function __sleep(): array
	{
		$this->cacheAll();

		$array = get_object_vars($this);
		unset($array['statement']);
		unset($array['hydrator']);
		unset($array['database']);

		return array_keys($array);
	}

	public function __debugInfo(): array
	{
		if(static::$debugInfo)
			return get_object_vars($this);

		return [
			'className' => $this->className,
			'exhausted' => $this->exhausted,
			'objects' => $this->objects,
		];
	}

	private function rowCount(): int
	{
		if($this->database instanceof \Reflexive\Core\Database && $this->database->getDSNPrefix() == 'sqlite') {
			if($this->count === null) {
				$this->cacheAll();
				$this->count = count($this->objects);
			}
			return $this->count;
			// $countQuery = $this->className::count()->where();
			// return $countQuery->execute($this->database);
		} else {
			return $this->statement?->rowCount();
		}
	}

	private function init(): void
	{
		if(!empty($this->statement) && $this->statement instanceof PDOStatement && null === $this->statement->errorCode())
			$this->count = null;//$this->rowCount();

		$this->index = 0;
		$this->lastKey = null;
		$this->lastObject = null;
		$this->valid = false;
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

		if($this->statement instanceof \Reflexive\Query\Composed) {
			$this->statement = $this->statement->prepare($this->database);
		}

		$result = $this->statement?->execute($params);
		$this->count = $this->database instanceof \Reflexive\Core\Database && $this->database->getDSNPrefix() == 'sqlite' ? null : $this->rowCount();

		$this->isList = (0 === $this->offset && $this->count !== null && $this->limit == $this->count);

		return $result ?? false;
	}

	/*
	 * Implements Iterator
	 */
	#[\Override]
	public function current(): mixed
	{
		return $this->lastObject;
	}

	private function fetchCurrent(): void
	{
		if($this->autoExecute && !empty($this->statement) && ($this->reset || null === $this->statement->errorCode())) {
			$this->execute();
		}

		if(!empty($this->statement) || $this->cache)
			[$this->lastKey, $this->lastObject] = $this->fetch($this->index);

		$this->valid = !empty($this->lastObject);
	}

	// fetch from statement or from cache, updates cache. Do not advance cursor.
	private function fetch(int $index): array
	{
		while($this->cache && isset($this->keys[$index])) { // exists in cache
			$key = $this->keys[$index];
			if(isset($this->objects[$key]))
				return [$key, $this->objects[$key]];

			unset($this->keys[$index]);
			$this->keys = array_values($this->keys);
		}

		if(!empty($this->statement)) { // not yet in cache
			$rs = $this->statement->fetch(
				PDO::FETCH_OBJ,
				PDO::FETCH_ORI_ABS,
				$index
			);

			if(empty($rs)) {
				return [null, null];
			} else {
				[$key, $object] = $this->hydrator->fetch($rs, $this->database);

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
			foreach($this as $_) {}
		}
	}

	public function asArray(bool $cacheAll = true): array
	{
		if($cacheAll)
			$this->cacheAll();

		return $this->objects;
	}

	#[\Override]
	public function next(): void
	{
		$this->index++;
		$this->fetchCurrent();
	}

	#[\Override]
	public function key(): mixed
	{
		return $this->lastKey;
	}

	#[\Override]
	public function valid(): bool
	{
		if(!$this->valid) {
			$this->exhausted = true;

			if($this->autoClose && isset($this->statement))
				$this->statement->closeCursor();
		}

		return $this->valid;
	}

	#[\Override]
	public function rewind(): void
	{
		$this->index = 0;
		$this->fetchCurrent();
	}

	#[\Override]
	public function has(SCRUDInterface $model): bool
	{
		if(enum_exists($model::class)) {
			$this->count = count($this->objects);
			/** @psalm-suppress NoInterfaceProperties */
			return isset($this[$model->name]);
		} else
			return isset($this[$model->getModelIdString()]);
	}

	/*
	 * Implements ArrayAccess
	 */
	#[\Override]
	public function offsetExists(mixed $key): bool
	{
		return null !== $this[$key];
	}

	#[\Override]
	public function offsetGet(mixed $key): mixed
	{
		if(isset($this->objects[$key])) {
			return $this->objects[$key];
		}

		if(empty($this->statement))
			return null;

		if($this->autoExecute && !empty($this->statement) && ($this->reset || null === $this->statement->errorCode())) {
			$this->execute();
		}

		if($this->fetchAbsolute && $this->isList && is_int($key)) {
			[, $object] = $this->fetch($key-1);
			return $object;
		} else {
			if(!$this->exhausted) {
				$count = count($this);
				for(
					$i = (($this->isList && is_int($key) && $key>=$count)? $count : $this->offset);
					$i <= (($this->isList && is_int($key)) ? $key : $this->limit ?? $count ?? 0);
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

	#[\Override]
	public function offsetSet(mixed $offset, mixed $value): void
	{
		if(isset($offset) && isset($this[$offset]) && $this[$offset] instanceof Model) { // already in collection
			$this->objects[$offset] = $value;
			if(false === $this->findKeyIndex($this->addedKeys, $offset) && $value->hasModifiedProperties() && false === $this->findKeyIndex($this->modifiedKeys, $offset)) { // is modified
				$this->modifiedKeys[] = $offset;
			}
			return;
		}

		if(is_null($offset)) {
			$this->objects[] = $value;
			$offset = array_key_last($this->objects);
		}

		$this->objects[$offset] = $value;
		if(false === $this->findKeyIndex($this->keys, $offset))
			$this->keys[] = $offset;
		if(false === $this->findKeyIndex($this->addedKeys, $offset))
			$this->addedKeys[] = $offset;

		if(null === $this->count)
			$this->count = 0;
		$this->count++;
	}

	#[\Override]
	public function offsetUnset(mixed $key): void
	{
		if($this->offsetExists($key)) {
			$object = $this[$key];
			unset($this->objects[$key]);
			$trackedKey = $key;
			$keyIndex = $this->findKeyIndex($this->keys, $key);
			if(false !== $keyIndex) {
				$trackedKey = $this->keys[$keyIndex];
				unset($this->keys[$keyIndex]);
			}

			if(false !== $this->findKeyIndex($this->addedKeys, $trackedKey)) {
				$addedKeyIndex = $this->findKeyIndex($this->addedKeys, $trackedKey);
				if(false !== $addedKeyIndex)
					unset($this->addedKeys[$addedKeyIndex]);
			} else {
				if(false === $this->findKeyIndex($this->removedKeys, $trackedKey)) {
					$this->removedKeys[] = $trackedKey;
					$this->removedObjects[$trackedKey] = $object;
				}
			}
			$this->count--;

			if(false !== $this->findKeyIndex($this->modifiedKeys, $trackedKey)) {
				$modifiedKeyIndex = $this->findKeyIndex($this->modifiedKeys, $trackedKey);
				if(false !== $modifiedKeyIndex)
					unset($this->modifiedKeys[$modifiedKeyIndex]);
			}

			$this->keys = array_values($this->keys); // re-index
			$this->addedKeys = array_values($this->addedKeys);
			$this->modifiedKeys = array_values($this->modifiedKeys);
		}
	}

	private function findKeyIndex(array $keys, mixed $key): int|string|false
	{
		foreach($keys as $index => $trackedKey) {
			if($trackedKey === $key)
				return $index;

			if((is_int($trackedKey) || is_string($trackedKey)) && (is_int($key) || is_string($key)) && (string)$trackedKey === (string)$key)
				return $index;
		}

		return false;
	}

	private function uniqueKeys(array ...$keySets): array
	{
		$keys = [];
		foreach($keySets as $keySet) {
			foreach($keySet as $key) {
				if(false === $this->findKeyIndex($keys, $key))
					$keys[] = $key;
			}
		}

		return $keys;
	}

	private function getCachedModifiedKeys(): array
	{
		$keys = [];
		foreach($this->objects as $key => $object) {
			if(
				$object instanceof Model
				&& $object->hasModifiedProperties()
				&& false === $this->findKeyIndex($this->addedKeys, $key)
				&& false === $this->findKeyIndex($this->removedKeys, $key)
			) {
				$keys[] = $key;
			}
		}

		return $keys;
	}

	/**
	 * @return array{created: int, updated: int, deleted: int}
	 */
	public function push(?PDO $database = null): array
	{
		if(!is_a($this->className, Model::class, true))
			throw new \LogicException('Can only push collections of '.Model::class.' models.');

		$result = [
			'created' => 0,
			'updated' => 0,
			'deleted' => 0,
		];

		$database ??= $this->database;
		if($database === null) {
			throw new \LogicException('No database for collection push.');
		}

		$remainingAddedKeys = [];
		foreach($this->addedKeys as $key) {
			if(!isset($this->objects[$key]) || !$this->objects[$key] instanceof Model) {
				$remainingAddedKeys[] = $key;
				continue;
			}

			$model = $this->objects[$key];
			if($this->className::create($model)->execute($database)) {
				$result['created']++;
				$newKey = $model->getModelIdString();
				if($newKey !== null && $newKey !== (string)$key) {
					unset($this->objects[$key]);
					$this->objects[$newKey] = $model;

					$keyIndex = $this->findKeyIndex($this->keys, $key);
					if(false !== $keyIndex)
						$this->keys[$keyIndex] = $newKey;
				}
			} else {
				$remainingAddedKeys[] = $key;
			}
		}

		$remainingModifiedKeys = [];
		foreach($this->uniqueKeys($this->modifiedKeys, $this->getCachedModifiedKeys()) as $key) {
			if(!isset($this->objects[$key]) || !$this->objects[$key] instanceof Model) {
				$remainingModifiedKeys[] = $key;
				continue;
			}

			$model = $this->objects[$key];
			if($this->className::update($model)->execute($database))
				$result['updated']++;
			else
				$remainingModifiedKeys[] = $key;
		}

		$remainingRemovedKeys = [];
		$remainingRemovedObjects = [];
		foreach($this->removedKeys as $key) {
			$model = $this->removedObjects[$key] ?? null;
			if(!$model instanceof Model)
				$model = $this->makeModelForKey($key);

			if($this->className::delete($model)->execute($database)) {
				$result['deleted']++;
				unset($this->objects[$key]);
				unset($this->removedObjects[$key]);
			} else {
				$remainingRemovedKeys[] = $key;
				$remainingRemovedObjects[$key] = $model;
			}
		}

		$this->addedKeys = array_values($remainingAddedKeys);
		$this->modifiedKeys = array_values($remainingModifiedKeys);
		$this->removedKeys = array_values($remainingRemovedKeys);
		$this->removedObjects = $remainingRemovedObjects;

		return $result;
	}

	private function makeModelForKey(int|string $key): Model
	{
		$model = new $this->className();
		$model->setModelId($key);

		return $model;
	}

	public function unsetAll(bool $keepObjects = true): int
	{
		if(!$this->exhausted && $this->autoExecute)
			$this->cacheAll();

		$persistedKeys = array_filter(
			$this->keys,
			fn($key): bool => false === $this->findKeyIndex($this->addedKeys, $key)
		);
		$affectedKeys = $this->uniqueKeys($this->addedKeys, $this->modifiedKeys, $persistedKeys);
		$this->removedKeys = $this->uniqueKeys($this->removedKeys, $this->modifiedKeys, $persistedKeys);
		foreach($this->removedKeys as $key) {
			if(isset($this->objects[$key]))
				$this->removedObjects[$key] = $this->objects[$key];
		}

		$this->addedKeys = $this->modifiedKeys = $this->keys = [];
		$this->count = 0;
		if(!$keepObjects)
			$this->objects = [];

		return count($affectedKeys);
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
	#[\Override]
	public function count(): int
	{
		if($this->autoExecute && !empty($this->statement) && ($this->reset || null === $this->statement->errorCode())) {

			$this->execute();
		}

		if($this->count === null && $this->database instanceof \Reflexive\Core\Database && $this->database->getDSNPrefix() == 'sqlite')
			$this->count = $this->rowCount();

		return $this->count ?? 0;
	}

	/*
	 * Implements JsonSerializable
	 */
	#[\Override]
	public function jsonSerialize(): mixed
	{
		$this->cacheAll();

		return array_values($this->objects);
	}
}
