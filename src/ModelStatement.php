<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Core\Comparator;
use Reflexive\Query;
use ReflectionClass;
use ReflectionUnionType;
use ReflectionIntersectionType;

abstract class ModelStatement
{
	// some config. Not in use.
	// public bool $useAccessors = false;

	// internals
	protected Query\Composed $query;
	protected ?Schema $schema;

	protected array $referencedStatements = [];

	// global caches ?
	protected static array $instanciators = [];

	// private ?\PDO $database;

	protected function __construct(
		protected string $modelClassName,
	)
	{}

	protected function initSchema() {
		if(!isset($this->schema)) {
			$schema = Schema::initFromAttributes($this->modelClassName);

			if(isset($schema) && !isset(self::$instanciators[$this->modelClassName])) {
				$classReflection = new ReflectionClass($this->modelClassName);

				// "instanciator" instantiate object without calling its constructor when needed by Collection or single pull
				self::$instanciators[$this->modelClassName] = function(object $rs, ?\PDO $database) use ($classReflection, $schema) {
					$this->modelClassName::initModelAttributes();
					$object = $classReflection->newInstanceWithoutConstructor();
					$object->setId($rs->id);

					foreach($schema->getColumns() as $propertyName => $column) {
						if(isset($column['columnName'])) {
							$propertyReflexion = $classReflection->getProperty($propertyName);
							$propertyReflexion->setAccessible(true);

							if($type = $propertyReflexion->getType()) {
								if($types = $type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType ? $type->getTypes() : [$type]) {
									foreach($types as $type) {
										if($type->allowsNull() && (!isset($rs->{$column['columnName']}) || is_null($rs->{$column['columnName']}))) { // is null and nullable
											$propertyReflexion->setValue($object, null);
											break;
										} else {
											if($type->isBuiltin()) { // PHP builtin types
												$propertyReflexion->setValue($object, $rs->{$column['columnName']});
												break;
											} else {
												$propertyReflexion->setValue($object, match($type->getName()) {
													'DateTime' => new \DateTime($rs->{$column['columnName']}),
												});
												break;
											}
										}
									}
								} else {
									$propertyReflexion->setValue($object, $rs->{$column['columnName']});
								}
							}
						}
					}

					foreach($schema->getReferences() as $propertyName => $reference) {
						if(isset(Schema::getCache()[$reference['type']])) {
							if(empty($database))
								throw new \InvalidArgumentException('No database to use for subsequent queries.');
							else {
								if($reference['cardinality'] == Cardinality::ManyToMany) {
									$propertyReflexion = $classReflection->getProperty($propertyName);
									$propertyReflexion->setValue($object, $reference['type']::search()->with($propertyName, Comparator::EQUAL, $object)->execute($database));
								}
							}
						}
					}

					$_POST['count']++;

					return [$rs->id, $object];
				};
			}

			$this->schema = $schema;
			$this->query->from($this->schema->getTableName());
		}
		// else {
		// 	throw new \Exception('Could not infer instanciator from Model attributes.');
		// }
	}

	protected function _prepare(\PDO $database): \PDOStatement
	{
		$this->initSchema();

		return $this->query->prepare($database);
	}

	public abstract function execute(\PDO $database);

	public function from(Schema $schema): static
	{
		$this->$schema = $schema;
		return $this;
	}

	public function where(string $propertyName, Comparator $comparator, string|int|float|array|bool $value = null): static
	{
		$this->initSchema();

		if($this->schema->hasColumn($propertyName)) {
			if(is_bool($value))
				$value = (int)$value;

			$this->query->where($this->schema->getTableName().'.'.$this->schema->getColumnName($propertyName), $comparator, $value);
		} elseif($this->schema->hasReference($propertyName)) {
			var_dump(__FILE__, __LINE__, $propertyName);
		} else {
			throw new \TypeError('Property "'.$propertyName.'" not found in Schema "'.$this->schema->getTableName().'"');
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
		return $this->query->__toString();
	}

	public function getQuery (): Query\Composed
	{
		$this->initSchema();
		// $this->query->bake();

		return $this->query;
	}
}
