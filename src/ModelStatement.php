<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Core\Comparator;
use Reflexive\Query;
use ReflectionClass;

class ModelStatement
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

	public function execute(\PDO $database): Collection
	{
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
					foreach($classReflection->getProperties() as $propertyReflection) {
						foreach($propertyReflection->getAttributes(ModelProperty::class) as $attributeReflection) {
							$modelAttribute = $attributeReflection->newInstance();

							if(!empty($modelAttribute->columnName))
								$schema->setColumnName($propertyReflection->getName(), $modelAttribute->columnName);
						}
					}

					self::$generators[static::class] = function($rs) use ($classReflection, $schema) {
						$this->modelClassName::initModelAttributes();
						$object = $classReflection->newInstanceWithoutConstructor();
						$object->setId($rs->id);

						foreach($schema->getColumns() as $propertyName => $columnName) {
							$propertyReflexion = $classReflection->getProperty($propertyName);
							$propertyReflexion->setAccessible(true);
							$propertyReflexion->setValue($object, $rs->$columnName);
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

		return new Collection(
			$this->query->prepare($database),
			self::$generators[static::class]
		);
	}

	// properties

	public function from(Schema $schema): static
	{
		$this->$schema = $schema;
		return $this;
	}

	public function search(string $name, Comparator $comparator, string|int|float|array $value = null): static
	{
		$this->query->where($name, $comparator, $value);
		return $this;
	}

	public function and(...$where): static
	{
		$this->query->and($where);
		return $this;
	}

	public function or(...$where): static
	{
		$this->or($where);
		return $this;
	}


	public function __toString(): string
	{
		return $this->query;
	}
}
