<?php

declare(strict_types=1);

namespace Reflexive\Model;

use ReflectionClass;

class Schema implements \JsonSerializable
{
	public bool $useModelNames = true;

	/*
	 * $columns[propertyName] = [
		'columnName' => string columnName,
		'type' => string columnType,
		'autoIncrement' => bool isAutoIncremented,
	  ];
	 */
	protected array $columns = [];
	protected array $columnNames = [];
	protected ?string $uIdColumnName = null;
	/*
	 * $references[propertyName] = [
		 'tableName' => string,
		 'columnName' => string,
		 'modelClassName' => string,
	 ];
	 */
	protected array $references = [];

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
	public function unsetColumn(int|string $key): void
	{
		unset($this->columns[$key]);
	}

	public function getColumnNames(): array
	{
		return $this->columnNames;
	}

	public function getColumnName(int|string $key): ?string
	{
		if($this->hasColumn($key))
			return $this->columns[$key]['columnName'];

		return null;
	}
	public function setColumnName(int|string $key, string $name): void
	{
		if($this->hasColumn($key))
			$this->columns[$key]['columnName'] = $name;
		else
			$this->columns[$key] = ['columnName' => $name];

		$this->columnNames[] = (!empty($this->tableName)? $this->tableName.'.':'').$name;
	}

	public function getUIdColumnName(): ?string
	{
		return $this->uIdColumnName;
	}
	public function setUIdColumnName(string $name): void
	{
		$this->uIdColumnName = $name;
	}

	public function hasReference(int|string $key): bool
	{
		return isset($this->references[$key]);
	}

	public function getReferenceCardinality(int|string $key): ?Cardinality
	{
		if($this->hasReference($key))
			return $this->references[$key]['cardinality'];

		return null;
	}
	public function setReferenceCardinality(int|string $key, Cardinality $cardinality): void
	{
		if($this->hasReference($key))
			$this->references[$key]['cardinality'] = $cardinality;
		else
			$this->references[$key] = ['cardinality' => $cardinality];
	}

	public function getReferenceColumnName(int|string $key): ?string
	{
		if($this->hasReference($key))
			return $this->references[$key]['columnName'];

		return null;
	}
	public function setReferenceColumnName(int|string $key, string $name): void
	{
		if($this->hasReference($key))
			$this->references[$key]['columnName'] = $name;
		else
			$this->references[$key] = ['columnName' => $name];
	}

	public function getReferenceForeignColumnName(int|string $key): ?string
	{
		if($this->hasReference($key))
			return $this->references[$key]['foreignColumnName'];

		return null;
	}
	public function setReferenceForeignColumnName(int|string $key, string $name): void
	{
		if($this->hasReference($key))
			$this->references[$key]['foreignColumnName'] = $name;
		else
			$this->references[$key] = ['foreignColumnName' => $name];
	}

	public function getReferenceForeignRightColumnName(int|string $key): ?string
	{
		if($this->hasReference($key))
			return $this->references[$key]['foreignRightColumnName'];

		return null;
	}
	public function setReferenceForeignRightColumnName(int|string $key, string $name): void
	{
		if($this->hasReference($key))
			$this->references[$key]['foreignRightColumnName'] = $name;
		else
			$this->references[$key] = ['foreignRightColumnName' => $name];
	}

	public function getReferenceForeignTableName(int|string $key): ?string
	{
		if($this->hasReference($key))
			return $this->references[$key]['foreignTableName'];

		return null;
	}
	public function setReferenceForeignTableName(int|string $key, string $name): void
	{
		if($this->hasReference($key))
			$this->references[$key]['foreignTableName'] = $name;
		else
			$this->references[$key] = ['foreignTableName' => $name];
	}

	public function getReferenceForeignRightTableName(int|string $key): ?string
	{
		if($this->hasReference($key))
			return $this->references[$key]['foreignTableName'];

		return null;
	}
	public function setReferenceForeignRightTableName(int|string $key, string $name): void
	{
		if($this->hasReference($key))
			$this->references[$key]['foreignRightTableName'] = $name;
		else
			$this->references[$key] = ['foreignRightTableName' => $name];
	}

	public function getReferenceType(int|string $key): ?string
	{
		if($this->hasReference($key))
			return $this->references[$key]['type'];

		return null;
	}
	public function setReferenceType(int|string $key, string $type): void
	{
		if($this->hasReference($key))
			$this->references[$key]['type'] = $type;
		else
			$this->references[$key] = ['type' => $type];
	}

	public function isReferenceNullable(int|string $key): ?bool
	{
		if($this->hasReference($key))
			return $this->references[$key]['nullable'] ?? null;

		return null;
	}
	public function setReferenceNullable(int|string $key, bool $nullable = true): void
	{
		if($this->hasReference($key))
			$this->references[$key]['nullable'] = $nullable;
		else
			$this->references[$key] = ['nullable' => $nullable];
	}

	public function getColumnType(int|string $key): ?string
	{
		if($this->hasColumn($key))
			return $this->columns[$key]['type'] ?? null;

		return null;
	}
	public function setColumnType(int|string $key, string $type): void
	{
		if($this->hasColumn($key))
			$this->columns[$key]['type'] = $type;
		else
			$this->columns[$key] = ['type' => $type];
	}

	public function isColumnNullable(int|string $key): ?bool
	{
		if($this->hasColumn($key))
			return $this->columns[$key]['nullable'] ?? null;

		return null;
	}
	public function setColumnNullable(int|string $key, bool $nullable = true): void
	{
		if($this->hasColumn($key))
			$this->columns[$key]['nullable'] = $nullable;
		else
			$this->columns[$key] = ['nullable' => $nullable];
	}

	public function isColumnUnique(int|string $key): ?bool
	{
		if($this->hasColumn($key))
			return $this->columns[$key]['unique'] ?? null;

		return null;
	}
	public function setColumnUnique(int|string $key, bool $unique = true): void
	{
		if($this->hasColumn($key))
			$this->columns[$key]['unique'] = $unique;
		else
			$this->columns[$key] = ['nullable' => $unique];
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

	public function getReferences(): array
	{
		return $this->references;
	}

	public function getTableName(): string
	{
		return $this->tableName;
	}

	public function isComplete(): bool
	{
		return $this->complete;
	}

	public function __toString()
	{
		return json_encode($this, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
	}

	public function jsonSerialize(): mixed
	{
		return [
			'complete' => $this->complete,
			'tableName' => $this->tableName,
			'uIdColumnName' => $this->uIdColumnName,
			'columns' => $this->columns,
			'references' => $this->references,
		];
		// $array['references']??= $this->references;
	}

	public function instanciator() {
		// var_dump('instanciator');
	}

	private static function reflectPropertiesAttributes(ReflectionClass $reflection, Schema &$schema, string $className): void
	{
		static::reflectColumnPropertiesAttributes($reflection, $schema);
		static::reflectReferencePropertiesAttributes($reflection, $schema, $className);
	}

	private static function reflectColumnPropertiesAttributes(ReflectionClass $reflection, Schema &$schema): void
	{
		foreach($reflection->getProperties() as $propertyReflection) {
			foreach($propertyReflection->getAttributes(Column::class) as $attributeReflection) {
				$modelAttribute = $attributeReflection->newInstance();

				if(!empty($modelAttribute->name))
					$schema->setColumnName($propertyReflection->getName(), $modelAttribute->name);
				elseif($schema->useModelNames)
					$schema->setColumnName($propertyReflection->getName(), $propertyReflection->getName());

				if(!empty($modelAttribute->type))
					$schema->setColumnType($propertyReflection->getName(), $modelAttribute->type);
				else {
					// should infer type in DB from type in Model. So instanciator should be known here.
				}

				if(!empty($modelAttribute->nullable))
					$schema->setColumnNullable($propertyReflection->getName(), $modelAttribute->nullable);
				else {
					// should infer nullable from type.
				}

				if(!empty($modelAttribute->unique))
					$schema->setColumnUnique($propertyReflection->getName(), $modelAttribute->unique);
				else {
					// should infer unique from ?.
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

	private static function reflectReferencePropertiesAttributes(ReflectionClass $reflection, Schema &$schema, string $className): void
	{
		foreach($reflection->getProperties() as $propertyReflection) {
			foreach($propertyReflection->getAttributes(Reference::class) as $attributeReflection) {
				$modelAttribute = $attributeReflection->newInstance();

				if($modelAttribute->type == $className)
					continue;

				if(!empty($modelAttribute->type)) {
					$referencedSchema = self::initFromAttributes($modelAttribute->type);

					if(isset($referencedSchema)) {
						$schema->setReferenceCardinality($propertyReflection->getName(), $modelAttribute->cardinality);

						if(!empty($modelAttribute->nullable))
							$schema->setReferenceNullable($propertyReflection->getName(), $modelAttribute->nullable);
						else {
							// should infer nullable from ?.
						}

						$schema->setReferenceColumnName($propertyReflection->getName(),  match($modelAttribute->cardinality) {
							Cardinality::OneToOne => $modelAttribute->columnName ?? $propertyReflection->getName(),
							Cardinality::OneToMany => $modelAttribute->columnName ?? $propertyReflection->getName(),
							Cardinality::ManyToMany => $modelAttribute->columnName ?? $schema->getUIdColumnName() ?? 'id',
						});

						switch($modelAttribute->cardinality) {
							case Cardinality::OneToOne:
							case Cardinality::OneToMany:
								if(!empty($modelAttribute->foreignTableName)) {
									$schema->setReferenceForeignTableName(
										$propertyReflection->getName(),
										$modelAttribute->foreignTableName
									);
								}
							break;
							case Cardinality::ManyToMany:
								$schema->setReferenceForeignTableName(
									$propertyReflection->getName(),
									$modelAttribute->foreignTableName ?? lcfirst($schema->getTableName()).'In'.$referencedSchema->getTableName()
								);
							break;
						}

						switch($modelAttribute->cardinality) {
							case Cardinality::OneToOne:
								$schema->setReferenceForeignColumnName(
									$propertyReflection->getName(),
									$schema->getReferenceColumnName($propertyReflection->getName())
								);
							break;
							case Cardinality::OneToMany:
								if(!empty($modelAttribute->foreignColumnName)) {
									$schema->setReferenceForeignColumnName(
										$propertyReflection->getName(),
										$modelAttribute->foreignColumnName
									);
								}
							break;
							case Cardinality::ManyToMany:
								$schema->setReferenceForeignColumnName(
									$propertyReflection->getName(),
									$modelAttribute->foreignColumnName ?? lcfirst($schema->getTableName()).ucfirst($schema->getReferenceColumnName($propertyReflection->getName()))
								);
							break;
						}

						if($modelAttribute->cardinality == Cardinality::ManyToMany)
							$schema->setReferenceForeignRightColumnName($propertyReflection->getName(), $modelAttribute->foreignRightColumnName ?? $modelAttribute->foreignColumnName ?? lcfirst($referencedSchema->getTableName()).ucfirst($schema->getReferenceColumnName($propertyReflection->getName())));

						$schema->setReferenceType($propertyReflection->getName(), $modelAttribute->type);
					} else {
						throw new \InvalidArgumentException('Referenced schema "'.$modelAttribute->type.'" does not exists, from schema "'.$className.'"');
					}
				}
			}
		}
	}

	public static function initFromAttributes(string $className): ?static
	{
		$schema = static::$schemas[$className] ?? null;

		if(!isset($schema)/* || !$schema->isComplete()*/) {
			try {
				$classReflection = new ReflectionClass($className);
				$useModelNames = true;

				// get attributes of class : SchemaAttribute
				foreach($classReflection->getAttributes(SchemaAttribute::class) as $attributeReflection) {
					$attribute = $attributeReflection->newInstance();
					$useModelNames = $attribute->useModelNames;
					break;
				}

				// get attributes of class : Table
				foreach($classReflection->getAttributes(Table::class) as $attributeReflection) {
					$attribute =  $attributeReflection->newInstance();
					if(!empty($attribute->tableName) || $useModelNames) {
						$schema = new static($attribute->tableName ?? $className);
						break;
					} else
						throw new \InvalidArgumentException('Could not infer Schema from Model attributes. No tableName.');
				}

				if(isset($schema)) {
					$schema->useModelNames = $useModelNames;
					// get attributes of traits properties
					foreach($classReflection->getTraits() as $traitReflection) {
						static::reflectPropertiesAttributes($traitReflection, $schema, $className);
					}

					// get attributes of properties
					static::reflectPropertiesAttributes($classReflection, $schema, $className);

					foreach($schema->getReferences() as $key => $reference) {
						$reference; // silence not used variable

						if($name = $schema->getColumnName($key)) {
							$schema->setReferenceColumnName($key, $name);

							$schema->unsetColumn($key);
						}
					}

					static::$schemas[$className] = $schema;
				}
			} catch (\ReflectionException $e) {
				throw new \InvalidArgumentException('Could not infer Schema from Model attributes. Reflection failed.', previous: $e);
			}
		}

		return $schema;
	}

	public static function getCache(): array
	{
		return self::$schemas;
	}
}
