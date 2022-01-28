<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Core\Comparator;
use ReflectionClass;

class Schema implements \JsonSerializable
{
	/*
	 * $columns[propertyName] = [
		'name' => string columnName,
		'type' => string columnType,
		'reference' => [
			'tableName' => string,
			'columnName' => string,
			'modelClassName' => string,
		],
		'autoIncrement' => bool isAutoIncremented,
	  ];
	 */
	protected array $columns = [];
	protected ?string $uIdColumnName;

	protected bool $complete = false;

	// global caches ?
	private static array $schemas = [];

	public function __construct(
		protected string $tableName,
	)
	{}

	public function hasColumn(int|string $key): bool
	{
		return isset($this->columns[$key]);
	}

	public function getColumnName(int|string $key): ?string
	{
		if($this->hasColumn($key))
			return $this->columns[$key]['name'];

		return null;
	}

	public function setColumnName(int|string $key, string $name): void
	{
		if($this->hasColumn($key))
			$this->columns[$key]['name'] = $name;
		else
			$this->columns[$key] = ['name' => $name];
	}

	public function getUIdColumnName(): ?string
	{
		return $this->uIdColumnName;
	}

	public function setUIdColumnName(string $name): void
	{
		$this->uIdColumnName = $name;
	}

	public function getColumnType(int|string $key): ?string
	{
		if($this->hasColumn($key))
			return $this->columns[$key]['type'] ?? null;

		return null;
	}

	public function setColumnReference(int|string $key, string $tableName, string $columnName, string $modelClassName): void
	{
		$array = [
			'tableName' => $tableName,
			'columnName' => $columnName,
			'modelClassName' => $modelClassName,
		];

		if($this->hasColumn($key))
			$this->columns[$key]['reference'] = $array;
		else
			$this->columns[$key] = ['reference' => $array];
	}

	public function getColumnReference(int|string $key): ?array
	{
		if($this->hasColumn($key))
			return $this->columns[$key]['reference'] ?? null;

		return null;
	}

	public function setColumnType(int|string $key, string $type): void
	{
		if($this->hasColumn($key))
			$this->columns[$key]['type'] = $type;
		else
			$this->columns[$key] = ['type' => $type];
	}

	public function setAutoIncrement(int|string $key, bool $state = true): void
	{
		if($this->hasColumn($key))
			$this->columns[$key]['autoIncrement'] = $state;
		else
			$this->columns[$key] = ['autoIncrement' => $state];
	}

	public function getColumns(): array
	{
		return $this->columns;
	}

	public function getTableName(): string
	{
		return $this->tableName;
	}

	public function __toString()
	{
		return json_encode($this, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
	}

	public function jsonSerialize(): mixed
	{
		return [
			'tableName' => $this->tableName,
			'uIdColumnName' => $this->uIdColumnName,
			'columns' => $this->columns,
		];
	}

	public function instanciator() {
		// var_dump('instanciator');
	}

	private static function reflectPropertiesAttributes(ReflectionClass $reflection, Schema &$schema): void
	{
		foreach($reflection->getProperties() as $propertyReflection) {
			// foreach($propertyReflection->getAttributes(ModelProperty::class) as $attributeReflection) {
			foreach($propertyReflection->getAttributes(Column::class) as $attributeReflection) {
				$modelAttribute = $attributeReflection->newInstance();

				if(!empty($modelAttribute->name))
					$schema->setColumnName($propertyReflection->getName(), $modelAttribute->name);

				if(!empty($modelAttribute->type))
					$schema->setColumnType($propertyReflection->getName(), $modelAttribute->type);
				else {
					// should infer type in DB from type in Model. So instanciator should be known here.
				}

				if(!empty($modelAttribute->arrayOf)) {
					$referencedSchema = self::initFromAttributes($modelAttribute->arrayOf);

					if(isset($referencedSchema)) {
						$schema->setColumnReference($propertyReflection->getName(), $referencedSchema->getTableName(), 'id', $modelAttribute->arrayOf);
					}
				}

				if(!empty($modelAttribute->autoIncrement)) {
					$schema->setAutoIncrement($propertyReflection->getName(), $modelAttribute->autoIncrement);
				}

				if(isset($modelAttribute->isId) && $modelAttribute->isId) {
					$schema->setUIdColumnName($propertyReflection->getName());
				}
			}
		}
	}

	public static function initFromAttributes(string $className): ?static
	{
		$schema = static::$schemas[$className] ?? null;

		if(!isset($schema)) {
			$classReflection = new ReflectionClass($className);
			// get attributes of class
			foreach($classReflection->getAttributes(ModelAttribute::class) as $attributeReflection) {
				$attribute =  $attributeReflection->newInstance();
				if(!empty($attribute->tableName)) {
					$schema = new static($attribute->tableName);
					break;
				} else
					throw new \InvalidArgumentException('Could not infer Schema from Model attributes. No tableName.');
			}

			if(isset($schema)) {
				// get attributes of properties
				static::reflectPropertiesAttributes($classReflection, $schema);

				// get attributes of traits properties
				foreach($classReflection->getTraits() as $traitReflection) {
					static::reflectPropertiesAttributes($traitReflection, $schema);
				}

				static::$schemas[$className] = $schema;
			}
		}

		return $schema;
	}

	public static function getCache(): array
	{
		return self::$schemas;
	}
}
