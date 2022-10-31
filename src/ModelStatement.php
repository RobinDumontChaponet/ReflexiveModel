<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Closure;
use DateTimeInterface;
use Reflexive\Core\Comparator;
use Reflexive\Query;
use ReflectionClass;
use ReflectionUnionType;
use ReflectionIntersectionType;

use Psr\SimpleCache;

abstract class ModelStatement
{
	// some config. Not in use.
	// public bool $useAccessors = false;

	// internals
	protected Query\Composed $query;
	protected ?Schema $schema = null;
	protected ?Closure $instanciator;

	protected array $referencedStatements = [];

	// global caches ?
	public static bool $useInternalCache = true;
	public static ?SimpleCache\CacheInterface $cache = null;
	public static int $cacheTTL = 120;
	protected static array $models = [];
	protected static array $instanciators = [];

	// stats
	public static int $instanciationCount = 0;

	protected static function _getModel(string $className, int|string|array $id): ?Model
	{
		return static::$models[$className][$id] ?? static::$cache?->get('model_'.$className.'_'.$id) ?? null;
	}

	protected static function _hasModel(string $className, int|string $id): bool
	{
		if(static::$useInternalCache) {
			return isset(static::$models[$className][$id]);
		} else
			return static::$cache?->has('model_'.$className.'_'.$id) ?? false;

		return false;
	}

	protected static function _setModel(Model $model): void
	{
		if(is_string($model->getModelId()) || empty($model->getModelId()) || $model->getModelId()<0 || enum_exists($model::class))
			return;

		if(static::$useInternalCache)
			static::$models[$model::class][$model->getModelIdString()] = $model;

		static::$cache?->set('model_'.$model::class.'_'.$model->getModelId(), $model, static::$cacheTTL);
	}

	// private ?\PDO $database;

	protected function __construct(
		protected string $modelClassName,
	)
	{}

	protected function init(): void
	{
		$schema = $this->schema ?? Schema::getSchema($this->modelClassName);

		if(!isset($schema))
			throw new \Exception('Could not infer schema from Model "'.$this->modelClassName.'" attributes.');

		if(empty(static::$instanciators[$this->modelClassName])) {
			$classReflection = new ReflectionClass($this->modelClassName);

			// "instanciator" instantiate object without calling its constructor when needed by Collection or single pull
			static::$instanciators[$this->modelClassName] = function(object $rs, ?\PDO $database) use (&$classReflection, $schema): array {
				// if(($object = static::_getModel($this->modelClassName, $rs->id)) !== null)

				if($schema->isSuperType()) {
					if(isset($rs->reflexive_subType) && is_a($rs->reflexive_subType, $this->modelClassName, true)) {
						$modelClassName = $rs->reflexive_subType;
						$classReflection = new ReflectionClass($modelClassName);
					} else
						throw new \LogicException('SUBTYPE DOES NOT EXISTS ?');
				} else {
					$modelClassName = $this->modelClassName;
				}

				$uids = $schema->getUIdColumnName();
				$id = '';
				foreach($uids as $uid) {
					$id.= $rs->$uid.', ';
				}
				$id = rtrim($id, ', ');

				if(($object = static::_getModel($modelClassName, $id)) !== null)
					return [$id, $object];

				if(is_a($modelClassName, Model::class, true)) { // is model
					$modelClassName::initModelAttributes();
					$object = $classReflection->newInstanceWithoutConstructor();

					$columns = $schema->getColumns();
					if(($superType = $this->schema->getSuperType()) !== null && ($superTypeSchema = Schema::getSchema($superType))) // is subType of $superType
						$columns+= $superTypeSchema->getColumns();

					foreach($columns as $propertyName => $column) {
						// var_dump($propertyName);

						if(isset($column['columnName'])) {
							$propertyReflection = $classReflection->getProperty($propertyName);
							$propertyReflection->setAccessible(true);

							if($type = $propertyReflection->getType()) {
								if($types = $type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType ? $type->getTypes() : [$type]) {
									foreach($types as $type) {
										if(!isset($rs->{$column['columnName']}) || is_null($rs->{$column['columnName']})) { // is not set or null
											if($type->allowsNull()) { // is nullable
												$propertyReflection->setValue($object, null);
												break;
											} else {
												throw new \TypeError('Property "'.$propertyName.'" of model "'.$modelClassName.'" cannot take null value from column "'.$column['columnName'].'"');
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

					if($schema->hasReferences() && empty($database))
						throw new \InvalidArgumentException('No database to use for subsequent queries.');

					foreach($schema->getReferences() as $propertyName => $reference) {
						if($referencedSchema = Schema::getSchema($reference['type'])) {
							$propertyReflection = $classReflection->getProperty($propertyName);

							switch($reference['cardinality']) {
								case Cardinality::OneToOne:
									$propertyReflection->setValue($object, $reference['type']::read()->where($reference['foreignColumnName'] ?? $referencedSchema->getUIdColumnNameString(), Comparator::EQUAL, $rs->{$reference['columnName']})->execute($database));
								break;
								case Cardinality::OneToMany:
									if($referencedSchema->isEnum())
										$propertyReflection->setValue($object, $reference['type']::from($rs->{$reference['columnName']}));
									else
										$propertyReflection->setValue($object, $reference['type']::read()->where($reference['foreignColumnName'] ?? $referencedSchema->getUIdColumnNameString(), Comparator::EQUAL, $rs->{$reference['columnName']})->execute($database));
								break;
								case Cardinality::ManyToOne:
									$propertyReflection->setValue($object, $reference['type']::read()->where($reference['foreignColumnName'] ?? $referencedSchema->getUIdColumnNameString(), Comparator::EQUAL, $rs->{$reference['columnName']})->execute($database));
									// if(isset($reference['inverse'])) {
									// 	$propertyReflection->setValue($object, $modelClassName::search()->where($reference['columnName'], Comparator::EQUAL, $object)->execute($database));
									// }
								break;
								case Cardinality::ManyToMany:
									$propertyReflection->setValue($object, $reference['type']::search()->with($propertyName, Comparator::EQUAL, $object)->execute($database));
								break;
							}
						}
					}
					static::_setModel($object);
					self::$instanciationCount++;

					return [$id, $object];

				} elseif(enum_exists($this->modelClassName) && is_a($this->modelClassName, SCRUDInterface::class, true)) { // is ModelEnum
					return [$rs->{$schema->getColumnName('id')}, constant($this->modelClassName.'::'.$rs->{$schema->getColumnName('id')})];
				} else {
					throw new \Exception('ModelStatement does not know how to create "'.$this->modelClassName.'".');
				}
			};
		}

		$this->schema = $schema;
		$this->query->from($this->schema->getTableName());

		// if(($superType = $this->schema->getSuperType()) !== null && ($superTypeSchema = Schema::getSchema($superType))) { // is subType of $superType
		// 	$this->query->join(
		// 		Query\Join::left,
		// 		$this->schema->getReferenceForeignTableName($superTypeSchema->getTableName()),
		// 		$this->schema->getReferenceForeignColumnName($superTypeSchema->getColumnName('id')),
		// 		Comparator::EQUAL,
		// 		$this->schema->getTableName(),
		// 		$this->schema->getUidColumnNameString(),
		// 	);
		// }

		return;

		throw new \Exception('Could not infer instanciator from Model "'.$this->modelClassName.'" attributes.');
	}

	protected function _prepare(\PDO $database): \PDOStatement
	{
		$this->init();

		return $this->query->prepare($database);
	}

	public abstract function execute(\PDO $database);

	public function from(Schema $schema): static
	{
		$this->$schema = $schema;
		return $this;
	}

	public function where(string $propertyName, Comparator $comparator, string|int|float|array|bool|Model|DateTimeInterface $value = null): static
	{
		if(empty($this->query->getConditions()))
			$this->init();

		if($this->schema->hasColumn($propertyName)) {
			$value = match(gettype($value)) {
				'boolean' => (int)$value,
				'object' => $value instanceof DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value->id,
				default => $value,
			};

			$this->query->where($this->schema->getTableName().'.'.$this->schema->getColumnName($propertyName), $comparator, $value);
		} elseif($this->schema->hasReference($propertyName)) {
			if(!is_object($value))
				throw new \TypeError('Can only reference "'.$propertyName.'" with object, '.gettype($value).' given.');

			// $referencedSchema = Schema::getSchema($value::class);
			// if(!isset($referencedSchema)) {
			// 	throw new \TypeError('Schema "'.$value::class.'" not set');
			// }

			if($this->schema->getReferenceCardinality($propertyName) == Cardinality::ManyToMany) {
				$this->query->join(
					Query\Join::inner,
					$this->schema->getReferenceForeignTableName($propertyName),
					$this->schema->getReferenceForeignColumnName($propertyName),
					Comparator::EQUAL,
					$this->schema->getTableName(),
					$this->schema->getUidColumnNameString(),
				);
				$this->query->and(
					$this->schema->getReferenceForeignTableName($propertyName).'.'.$this->schema->getReferenceForeignRightColumnName($propertyName),
					$comparator,
					$value->getModelId(),
				);
			}
		} else {
			throw new \TypeError('Property (or Reference) "'.$propertyName.'" not found in Schema "'.$this->schema->getTableName().'"');
		}

		return $this;
	}

	public function and(...$where): static
	{
		$this->query->and();
		$this->where(...$where);

		return $this;
	}

	public function or(...$where): static
	{
		$this->query->or();
		$this->where(...$where);

		return $this;
	}

	public function limit(int $limit = null): static
	{
		$this->query->limit($limit);

		return $this;
	}
	public function offset(int $offset = null): static
	{
		$this->query->offset($offset);

		return $this;
	}

	public function __toString(): string
	{
		return $this->getQuery()->__toString();
	}

	public function getQuery (): Query\Composed
	{
		$this->init();
		// $this->query->bake();

		return $this->query;
	}

	 public function getInstanciator(): ?Closure
	 {
		 $this->init();

		 return static::$instanciators[$this->modelClassName] ?? null;
	 }
}
