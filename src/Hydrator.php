<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Core\Comparator;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;
use ReflectionIntersectionType;
use Psr\SimpleCache;

class Hydrator
{
	protected static array $hydrators = [];

	protected string $modelClassName;
	protected Schema $schema;
	protected ReflectionClass $classReflection;

	protected array $models = [];
	protected array $propertyReflections = [];
	protected array $propertyTypes = [];
	protected ?array $columnPlan = null;
	protected ?array $referencePlan = null;

	// global caches ?
	public static bool $useInternalCache = true;
	public static ?SimpleCache\CacheInterface $cache = null;
	public static int $cacheTTL = 120;

	// stats
	public static int $fetchCount = 0;

	private function normalizeId(int|string|array $id): string
	{
		if(is_array($id)) {
			$parts = [];
			foreach($id as $value) {
				$parts[] = $this->normalizeId($value);
			}

			return implode(', ', $parts);
		}

		return (string)$id;
	}

	private function getCacheKey(int|string|array $id): string
	{
		return 'model_'.$this->modelClassName.'_'.$this->normalizeId($id);
	}

	protected function _getModel(int|string|array $id): ?Model
	{
		$id = $this->normalizeId($id);

		return $this->models[$id] ?? static::$cache?->get($this->getCacheKey($id)) ?? null;
	}
	public function getFromCache(int|string|array $id): ?Model
	{
		return $this->_getModel($id);
	}

	protected function _hasModel(int|string $id): bool
	{
		$id = $this->normalizeId($id);

		if(static::$useInternalCache) {
			return isset($this->models[$id]);
		}

		return static::$cache?->has($this->getCacheKey($id)) ?? false;
	}

	protected function _setModel(int|string|array $id, Model $model): void
	{
		$id = $this->normalizeId($id);
		if($id === '' || enum_exists($model::class))
			return;

		if(static::$useInternalCache)
			$this->models[$id] = $model;

		if(static::$cache !== null && !(new \ReflectionObject($model))->isUninitializedLazyObject($model))
			static::$cache->set($this->getCacheKey($id), $model, static::$cacheTTL);
	}

	private function __construct(string $modelClassName)
	{
		$this->modelClassName = $modelClassName;
		$this->schema = Schema::getSchema($modelClassName);
		$this->classReflection = new ReflectionClass($modelClassName);

		if(!isset($this->schema))
			throw new \Exception('Could not infer schema from Model "'.$modelClassName.'" attributes.');
	}

	private function makeGhost(string $className, string $columnName, mixed $id, ?\PDO $database): object
	{
		$hydrator = self::getHydrator($className);
		$ghost = $hydrator->classReflection->newLazyGhost(function (object $ghost) use ($className, $columnName, $id, $database, $hydrator): void {
			$statement = $className::read()
				->where(Condition::EQUAL($columnName, $id))
				->getQuery()
				->prepare($database);

			$statement->execute();
			if($rs = $statement->fetch(\PDO::FETCH_OBJ)) {
				$hydrator->_fetch($ghost, $rs, $database);
				(new \ReflectionObject($ghost))->markLazyObjectAsInitialized($ghost);
			} else {
				throw new \LogicException('Could not lazy-load "'.$className.'" with "'.$columnName.'" = "'.$id.'".');
			}
		});

		if($columnName === $hydrator->schema->getUIdColumnNameString())
			$hydrator->_setModel($id, $ghost);

		return $ghost;
	}

	private function makeProxy(string $className, string $columnName, mixed $id, ?\PDO $database): object
	{
		return self::getHydrator($className)->classReflection->newLazyProxy(function () use ($className, $columnName, $id, $database): object {
			$model = $className::read()
				->where(Condition::EQUAL($columnName, $id))
				->execute($database);

			if(!$model instanceof Model)
				throw new \LogicException('Could not lazy-load "'.$className.'" with "'.$columnName.'" = "'.$id.'".');

			return $model;
		});
	}

	private function makeReference(string $className, Schema $schema, string $columnName, mixed $id, ?\PDO $database): ?object
	{
		if($schema->isSuperType()) {
			$hydrator = self::getHydrator($className);
			if($hydrator->classReflection->isAbstract())
				return $className::read()->where(Condition::EQUAL($columnName, $id))->execute($database);

			return $this->makeProxy($className, $columnName, $id, $database);
		}

		return $this->makeGhost($className, $columnName, $id, $database);
	}

	private static function allowsNull(?\ReflectionType $type): bool {
		if(!$type)
			return true;

		if($type instanceof \ReflectionUnionType) {
			foreach($type->getTypes() as $t) {
				if($t->getName() === 'null')
					return true;
			}
			return false;
		}
		return $type->allowsNull();
	}


	private function getPropertyReflection(string $propertyName): \ReflectionProperty
	{
		return $this->propertyReflections[$propertyName] ??= $this->classReflection->getProperty($propertyName);
	}

	private function getPropertyTypes(\ReflectionProperty $propertyReflection): array
	{
		return $this->propertyTypes[$propertyReflection->getName()] ??= (
			($type = $propertyReflection->getType())
				? ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType ? $type->getTypes() : [$type])
				: []
		);
	}

	private function getColumnPlan(): array
	{
		if($this->columnPlan !== null)
			return $this->columnPlan;

		$columns = $this->schema->getColumns();
		$superType = $this->schema->getSuperType();
		if($superType !== null) {
			$superTypeSchema = Schema::getSchema($superType);
			if($superTypeSchema !== null)
				$columns+= $superTypeSchema->getColumns();
		}

		$this->columnPlan = [];
		foreach($columns as $propertyName => $column) {
			if(!isset($column['columnName']))
				continue;

			$propertyReflection = $this->getPropertyReflection($propertyName);
			$this->columnPlan[] = [
				'propertyName' => $propertyName,
				'property' => $propertyReflection,
				'columnName' => $column['columnName'],
				'nullable' => $propertyReflection->getType()?->allowsNull() ?? true,
				'types' => $this->buildTypePlan($propertyReflection, $column),
			];
		}

		return $this->columnPlan;
	}

	private function buildTypePlan(\ReflectionProperty $propertyReflection, array $column): array
	{
		$types = [];
		foreach($this->getPropertyTypes($propertyReflection) as $type) {
			if(!$type instanceof ReflectionNamedType)
				continue;

			$typeName = $type->getName();
			if($type->isBuiltin()) {
				$types[] = [
					'name' => $typeName,
					'kind' => $typeName == 'array' && ($column['type'] ?? null) == 'json' ? 'json-array' : 'builtin',
				];
			} elseif(enum_exists($typeName)) {
				$types[] = [
					'name' => $typeName,
					'kind' => 'enum',
				];
			} elseif(class_exists($typeName, true)) {
				$types[] = [
					'name' => $typeName,
					'kind' => match($typeName) {
						\DateTime::class => 'datetime',
						'stdClass' => 'json-object',
						default => 'object',
					},
				];
			}
		}

		return $types;
	}

	private function hydrateColumn(object $object, object $rs, array $column): void
	{
		$propertyReflection = $column['property'];
		$columnName = $column['columnName'];

		if(!isset($rs->{$columnName}) || is_null($rs->{$columnName})) {
			if($column['nullable']) {
				$propertyReflection->setValue($object, null);
				return;
			}

			throw new \TypeError('Property "'.$column['propertyName'].'" of model "'.$this->modelClassName.'" cannot take null value from column "'.$columnName.'"');
		}

		$value = $rs->{$columnName};
		if(empty($column['types'])) {
			$propertyReflection->setValue($object, $value);
			return;
		}

		$typeName = $column['types'][0]['name'];
		$hydratedValue = $value;
		switch($column['types'][0]['kind']) {
			case 'json-array':
				$hydratedValue = json_decode($value, true);
			break;
			case 'enum':
				$hydratedValue = constant($typeName.'::'.$value);
			break;
			case 'datetime':
				$hydratedValue = new \DateTime($value);
			break;
			case 'json-object':
				$hydratedValue = json_decode($value);
			break;
			case 'object':
				$hydratedValue = new $typeName($value);
			break;
		}

		$propertyReflection->setValue(
			$object,
			$hydratedValue
		);
	}

	private function getReferencePlan(): array
	{
		if($this->referencePlan !== null)
			return $this->referencePlan;

		$references = $this->schema->getReferences();
		$superType = $this->schema->getSuperType();
		if($superType !== null) {
			$superTypeSchema = Schema::getSchema($superType);
			if($superTypeSchema !== null)
				$references+= $superTypeSchema->getReferences();
		}

		$this->referencePlan = [];
		foreach($references as $propertyName => $reference) {
			if(isset($superType) && $superType == $reference['type'])
				continue;

			$referencedSchema = Schema::getSchema($reference['type']);
			if(!$referencedSchema)
				continue;

			$this->referencePlan[] = [
				'propertyName' => $propertyName,
				'property' => $this->getPropertyReflection($propertyName),
				'reference' => $reference,
				'referencedSchema' => $referencedSchema,
				'readColumnName' => $reference['foreignColumnName'] ?? $referencedSchema->getUIdColumnNameString(),
			];
		}

		return $this->referencePlan;
	}

	private function _fetch(object &$object, object $rs, ?\PDO $database): void
	{
		$statement = null;
		if($rs instanceof ModelStatement) {
			$statement = $rs->getQuery();
		}
		if($rs instanceof \Reflexive\Query\Composed) {
			$statement = $rs;
		}
		if($statement instanceof \Reflexive\Query\Composed) {
			$statement = $statement->prepare($database);
			$statement->execute();
		}

		if($statement) {
			$rs = $statement->fetch(\PDO::FETCH_OBJ);
		}

		foreach($this->getColumnPlan() as $column) {
			$this->hydrateColumn($object, $rs, $column);
		}

		$references = $this->getReferencePlan();
		if(!empty($references) && empty($database))
			throw new \InvalidArgumentException('No database to use for subsequent queries.');

		foreach($references as $plan) {
			$propertyReflection = $plan['property'];
			$reference = $plan['reference'];
			$referencedSchema = $plan['referencedSchema'];
			$propertyName = $plan['propertyName'];

			switch($reference['cardinality']) {
				case Cardinality::OneToOne:
					$id = isset($reference['columnName']) ? ($rs->{$reference['columnName']} ?? null) : null;
					if($id === null) {
						if($referencedSchema->isSuperType() || static::allowsNull($propertyReflection->getType()))
							$propertyReflection->setValue($object, null);
						else
							throw new \TypeError('Reference column "'.$reference['columnName'].'" in schema "'.$this->modelClassName.'" cannot take null value from property (reference) "'.$propertyName.'"');
					} else {
						$propertyReflection->setValue(
							$object,
							$this->makeReference($reference['type'], $referencedSchema, $plan['readColumnName'], $id, $database)
						);
					}
				break;
				case Cardinality::OneToMany:
					$id = $rs->{$reference['columnName']} ?? null;
					if($id === null) {
						if($referencedSchema->isEnum() || static::allowsNull($propertyReflection->getType()))
							$propertyReflection->setValue($object, null);
						else
							throw new \TypeError('Reference column "'.$reference['columnName'].'" in schema "'.$this->modelClassName.'" cannot take null value from property (reference) "'.$propertyName.'"');
					} elseif($referencedSchema->isEnum()) {
						$propertyReflection->setValue($object, $reference['type']::from($id));
					} else {
						$propertyReflection->setValue(
							$object,
							$this->makeReference($reference['type'], $referencedSchema, $plan['readColumnName'], $id, $database)
						);
					}
				break;
				case Cardinality::ManyToOne:
					// $propertyReflection->setValue($object, $reference['type']::read()->where($reference['foreignColumnName'] ?? $referencedSchema->getUIdColumnNameString(), Comparator::EQUAL, $rs->{$reference['columnName']})->execute($database));
					// if(isset($reference['inverse'])) {
							$propertyReflection->setValue(
								$object,
								$reference['type']::search()
									->where(Condition::EQUAL(
										$propertyName,
										$object
									)
								)->execute($database)
							);
					// }
				break;
				case Cardinality::ManyToMany:
					$propertyReflection->setValue(
						$object,
						$reference['type']::search()
							->with($propertyName, Comparator::EQUAL, $object)
							->execute($database)
					);
				break;
			}
		}
	}

	public function fetch(object $rs, ?\PDO $database, bool $lazy = false): array
	{
		$uids = $this->schema->getUIdColumnName();
		$id = '';
		foreach($uids as $uid) {
			$id.= $rs->$uid.', ';
		}
		$id = rtrim($id, ', ');

		if($this->schema->isSuperType()) {
			if(isset($rs->reflexive_subType) && is_a($rs->reflexive_subType, $this->modelClassName, true)) {
				$subTypeQuery = new Read($rs->reflexive_subType);
				return [
					$id,
					$subTypeQuery->where(Condition::EQUAL(
						$this->schema->getUIdColumnNameString(),
						$id
					))->execute($database)
				];
			} else
				throw new \LogicException('SUBTYPE DOES NOT EXISTS ?');
		}

		if(($object = $this->_getModel($id)) !== null)
			return [$id, $object];

		if(is_a($this->modelClassName, Model::class, true)) { // is model
				if($lazy) { // lazy
					$object = $this->classReflection->newLazyGhost(function (object $ghost) use ($rs, $database): void {
						$this->_fetch($ghost, $rs, $database);
						(new \ReflectionObject($ghost))->markLazyObjectAsInitialized($ghost);
					});
				} else { // eager
					$object = $this->classReflection->newInstanceWithoutConstructor();
					$this->_fetch($object, $rs, $database);
				}

			$this->_setModel($id, $object);
			self::$fetchCount++;

			return [$id, $object];
		} elseif(enum_exists($this->modelClassName) && is_a($this->modelClassName, SCRUDInterface::class, true)) { // is ModelEnum
			return [$rs->{$this->schema->getColumnName('id')}, constant($this->modelClassName.'::'.$rs->{$this->schema->getColumnName('id')})];
		} else {
			throw new \Exception('Hydrator does not know how to create "'.$this->modelClassName.'".');
		}
	}

	public static function getHydrator(string $modelClassName): static
	{
		if(!isset(static::$hydrators[$modelClassName])) {
			static::$hydrators[$modelClassName] = new static($modelClassName);
		}

		return static::$hydrators[$modelClassName];
	}
}
