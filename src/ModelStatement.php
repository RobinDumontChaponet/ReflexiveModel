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
	public bool $useAccessors = false;

	// internals
	protected Query\Composed $query;
	protected ?Schema $schema;

	// global caches ?
	private static array $schemas = [];
	protected static array $generators = [];

	protected function __construct(
		protected string $modelClassName,
	)
	{}

	private function reflectPropertiesAttributes(ReflectionClass $reflection, Schema &$schema): void
	{
		foreach($reflection->getProperties() as $propertyReflection) {
			foreach($propertyReflection->getAttributes(ModelProperty::class) as $attributeReflection) {
				$modelAttribute = $attributeReflection->newInstance();

				if(!empty($modelAttribute->columnName))
					$schema->setColumnName($propertyReflection->getName(), $modelAttribute->columnName);

				if(!empty($modelAttribute->autoIncrement))
					$schema->setAutoIncrement($propertyReflection->getName(), $modelAttribute->autoIncrement);
			}
		}
	}

	protected function initSchema() {
		if(!isset($this->schema)) {
			if(!isset(self::$schemas[$this->modelClassName])) {
				$classReflection = new ReflectionClass($this->modelClassName);
				// get attributes of class
				foreach($classReflection->getAttributes(ModelAttribute::class) as $attributeReflection) {
					$attribute =  $attributeReflection->newInstance();
					if(!empty($attribute->tableName))
						$schema = new Schema($attribute->tableName);
				}

				if(isset($schema)) {
					// get attributes of properties
					$this->reflectPropertiesAttributes($classReflection, $schema);

					// get attributes of traits properties
					foreach($classReflection->getTraits() as $traitReflection) {
						$this->reflectPropertiesAttributes($traitReflection, $schema);
					}

					// "generator" instantiate object without calling its constructor when needed by Collection
					self::$generators[$this->modelClassName] = function($rs) use ($classReflection, $schema) {
						$this->modelClassName::initModelAttributes();
						$object = $classReflection->newInstanceWithoutConstructor();
						$object->setId($rs->id);

						foreach($schema->getColumns() as $propertyName => $column) {
							$propertyReflexion = $classReflection->getProperty($propertyName);
							$propertyReflexion->setAccessible(true);

							$type = $propertyReflexion->getType();
							if(isset($type)) {
								if($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType)
									$types = $type->getTypes();
									// throw new \TypeError('Cannot use union and intersection type');
								else
									$types = [$type];
							}
							if(!empty($types)) {
								foreach($types as $type) {
									if($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType)
										$types = $type->getTypes();
									else
										$types = [$type];

									if(is_null($rs->{$column['name']}) && $type->allowsNull()) { // is null and nullable
										$propertyReflexion->setValue($object, $rs->{$column['name']});
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

						return [$rs->id, $object];
					};

					self::$schemas[$this->modelClassName] = $schema;
					$this->schema = $schema;
				} else {
					throw new \Exception('Could not infer Schema from Model attributes.');
				}
			}

			$this->schema = self::$schemas[$this->modelClassName];
		}

		$this->query->from($this->schema->getTableName());
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
		$this->or();
		$this->where($where);

		return $this;
	}

	public function __toString(): string
	{
		return $this->query;
	}
}
