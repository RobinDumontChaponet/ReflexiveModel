<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Closure;
use PDOStatement;
use Reflexive\Core\Comparator;
use ReflectionClass;
use ReflectionUnionType;
use ReflectionIntersectionType;
use Psr\SimpleCache;

class Hydrator
{
	protected static array $hydrators = [];

	protected string $modelClassName;
	protected Schema $schema;

	protected array $models = [];

	// global caches ?
	public static bool $useInternalCache = true;
	public static ?SimpleCache\CacheInterface $cache = null;
	public static int $cacheTTL = 120;

	// stats
	public static int $fetchCount = 0;

	protected function _getModel(int|string|array $id): ?Model
	{
		return $this->models[$id] ?? static::$cache?->get('model_'.$this->modelClassName.'_'.$id) ?? null;
	}
	public function getFromCache(int|string|array $id): ?Model
	{
		return $this->_getModel($id);
	}

	protected function _hasModel(int|string $id): bool
	{
		if(static::$useInternalCache) {
			return isset($this->models[$id]);
		} else
			return static::$cache?->has('model_'.$this->modelClassName.'_'.$id) ?? false;

		return false;
	}

	protected function _setModel(Model $model): void
	{
		if(is_string($model->getModelId()) || empty($model->getModelId()) || $model->getModelId()<0 || enum_exists($model::class))
			return;

		if(static::$useInternalCache)
			$this->models[$model->getModelIdString()] = $model;

		static::$cache?->set('model_'.$model::class.'_'.$model->getModelIdString(), $model, static::$cacheTTL);
	}

	private function __construct(string $modelClassName)
	{
		$this->modelClassName = $modelClassName;
		$this->schema = Schema::getSchema($modelClassName);

		if(!isset($this->schema))
			throw new \Exception('Could not infer schema from Model "'.$modelClassName.'" attributes.');
	}

	private function makeGhost(string $className, object $rs, $database): object {
		$classReflection = new ReflectionClass($className);
		return $classReflection->newLazyProxy(function (object $proxy) use ($rs, $database, $className) {
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

			return self::getHydrator($className)->fetch($rs, $database, true)[1];
			// (new \ReflectionObject($ghost))->markLazyObjectAsInitialized($ghost);
		});
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


	private function _fetch(object &$object, object $rs, ?\PDO $database): void
	{
		$classReflection = new ReflectionClass($this->modelClassName);

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

		$columns = $this->schema->getColumns();
		$superType = $this->schema->getSuperType();
		if($superType !== null) { // is subType of $superType
			$superTypeSchema = Schema::getSchema($superType);
			$columns+= $superTypeSchema->getColumns();
		}

		foreach($columns as $propertyName => $column) {
			if(isset($column['columnName'])) {
				$propertyReflection = $classReflection->getProperty($propertyName);
				// $propertyReflection->setAccessible(true);

				if($type = $propertyReflection->getType()) {
					if($types = $type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType ? $type->getTypes() : [$type]) {
						foreach($types as $type) {
							if(!isset($rs->{$column['columnName']}) || is_null($rs->{$column['columnName']})) { // is not set or null
								if($type->allowsNull()) { // is nullable
									$propertyReflection->setValue($object, null);
									break;
								} else {
									throw new \TypeError('Property "'.$propertyName.'" of model "'.$this->modelClassName.'" cannot take null value from column "'.$column['columnName'].'"');
								}
							} else {
								$value = $rs->{$column['columnName']};

								if($type->isBuiltin()) { // PHP builtin types
									$propertyReflection->setValue($object, $value);
									break;
								} else {
									/** @psalm-var class-string $typeName */
									$typeName = $type->getName();

									if(enum_exists($typeName)) { // PHP enum
										$propertyReflection->setValue(
											$object,
											constant($typeName.'::'.$value)
											// $typeName::tryFrom($value)
										);
										break;
									} elseif(class_exists($typeName, true)) { // object
										$propertyReflection->setValue(
											$object,
											match($typeName) {
												\DateTime::class => new \DateTime($value),
												'stdClass' => json_decode($value),
												default => new $typeName($value)
											}
										);
										break;
									}
								}
							}
						}
					} else {
						$propertyReflection->setValue($object, $rs->{$column['columnName']});
					}
				}
			}
		}

		$references = $this->schema->getReferences();
		if(isset($superType)) // is subType of $superType
			$references+= $superTypeSchema->getReferences();

		// if(isset($subTypeSchema)) // is superType
		// 	$references+= array_diff_key($subTypeSchema->getReferences(), array_flip($this->schema->getUIdPropertyName()));

		if(!empty($references) && empty($database))
			throw new \InvalidArgumentException('No database to use for subsequent queries.');

		foreach($references as $propertyName => $reference) {
			if($referencedSchema = Schema::getSchema($reference['type'])) {
				if(isset($superType) && $superType == $reference['type'])
					continue;

				$propertyReflection = $classReflection->getProperty($propertyName);

				switch($reference['cardinality']) {
					case Cardinality::OneToOne:
						$propertyReflection->setValue(
							$object,
							$referencedSchema->isSuperType() || static::allowsNull($propertyReflection->getType())
								? $statement->execute($database)
								: $this->makeGhost(
									$reference['type'],
									$statement,
									$database,
									$classReflection
								)
						);
					break;
					case Cardinality::OneToMany:
						if($referencedSchema->isEnum())
							$propertyReflection->setValue($object, $reference['type']::from($rs->{$reference['columnName']}));
						else {
							$statement = $reference['type']::read()
								->where(Condition::EQUAL(
									$reference['foreignColumnName'] ?? $referencedSchema->getUIdColumnNameString(),
									$rs->{$reference['columnName']}
								)
							);

							$propertyReflection->setValue(
								$object,
								// $statement->execute($database)
								$referencedSchema->isSuperType() || static::allowsNull($propertyReflection->getType())
									? $statement->execute($database)
									: $this->makeGhost(
										$reference['type'],
										$statement,
										$database,
										$classReflection
									)
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
										$reference['columnName'],
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
			$this->modelClassName::initModelAttributes();
			$classReflection = new ReflectionClass($this->modelClassName);

			if($lazy) { // lazy
				$object = $classReflection->newLazyGhost(function (object $ghost) use ($rs, $database): void {
					$this->_fetch($ghost, $rs, $database, true);
					(new \ReflectionObject($ghost))->markLazyObjectAsInitialized($ghost);
				});
			} else { // eager
				$object = $classReflection->newInstanceWithoutConstructor();
				$this->_fetch($object, $rs, $database, false);
			}

			$this->_setModel($object);
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
