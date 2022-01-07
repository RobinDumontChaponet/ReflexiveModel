<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Core\Comparator;
use Reflexive\Query;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;
use ReflectionIntersectionType;

abstract class ModelStatement
{
	// some config. Not in use.
	// public bool $useAccessors = false;

	// internals
	protected Query\Composed $query;
	protected ?Schema $schema;

	// global caches ?
	protected static array $instanciators = [];

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
				self::$instanciators[$this->modelClassName] = function($rs) use ($classReflection, $schema) {
					$this->modelClassName::initModelAttributes();
					$object = $classReflection->newInstanceWithoutConstructor();
					$object->setId($rs->id);

					foreach($schema->getColumns() as $propertyName => $column) {
						if(isset($column['name'])) {
							$propertyReflexion = $classReflection->getProperty($propertyName);
							$propertyReflexion->setAccessible(true);

							if($type = $propertyReflexion->getType()) {
								if($types = $type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType ? $type->getTypes() : [$type]) {
									foreach($types as $type) {
										if(is_null($rs->{$column['name']}) && $type->allowsNull()) { // is null and nullable
											$propertyReflexion->setValue($object, null);
											break;
										} else {
											if($type->isBuiltin()) { // PHP builtin types
												$propertyReflexion->setValue($object, $rs->{$column['name']});
												break;
											} else {
												$propertyReflexion->setValue($object, match($type->getName()) {
													'DateTime' => new \DateTime($rs->{$column['name']}),
												});
												break;
											}
										}
									}
								} else {
									$propertyReflexion->setValue($object, $rs->{$column['name']});
								}
							}
						}
					}

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

	protected function _execute(\PDO $database): \PDOStatement
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

			$this->query->where($this->schema->getColumnName($propertyName), $comparator, $value);
		} else {
			throw new \TypeError('Property "'.$propertyName.'" not found in Schema "'.$this->schema->getTableName().'"');
		}

		return $this;
	}

	public function and(...$where): static
	{
		$this->query->and();
		$this->where($where);

		return $this;
	}

	public function or(...$where): static
	{
		$this->query->or();
		$this->where($where);

		return $this;
	}

	public function __toString(): string
	{
		return $this->query;
	}
}
