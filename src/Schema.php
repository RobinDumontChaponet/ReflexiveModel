<?php

declare(strict_types=1);

namespace Reflexive\Model;

use ReflectionClass;
use ReflectionUnionType;
use ReflectionIntersectionType;

use Composer\Script\Event;

class Schema implements \JsonSerializable
{
	public bool $useModelNames = true;

	/*
	 * $columns[string propertyName] = [
		'columnName' => string,
		'type' => string,
		'autoIncrement' => bool,
	  ];
	 */
	protected array $columns = [];
	/*
	 * $columnNames[string propertyName] = string columnName;
	 */
	protected array $columnNames = [];
	protected ?string $uIdColumnName = null;
	/*
	 * $references[string propertyName] = [
		 'tableName' => string,
		 'columnName' => string,
		 'modelClassName' => string,
	 ];
	 */
	protected array $references = [];

	protected bool $complete = false;

	// global caches ?
	// private static bool $cache = true;
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
		return array_map(fn($value): string => $this->tableName.$value, $this->columnNames);
	}

	public function getColumnName(int|string $key): ?string
	{
		if($this->hasColumn($key))
			return $this->columns[$key]['columnName'];

		return null;
	}
	public function setColumnName(int|string $key, string $name): void
	{
		if($this->hasColumn($key)) {
			$this->columns[$key]['columnName'] = $name;
		} else {
			$this->columns[$key] = ['columnName' => $name];
			$this->columnNames[$key] = $name;
		}
	}

	public function getUIdColumnName(): ?string
	{
		return $this->uIdColumnName;
	}
	public function setUIdColumnName(string $name): void
	{
		$this->uIdColumnName = $name;
	}

	public function getUIdColumnType(): ?string
	{
		return $this->getColumnType($this->uIdColumnName);
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
		elseif($this->hasReference($key)) {
			return self::initFromAttributes($this->references[$key]['type'])->getUIdColumnType();
		}

		// var_dump($this->references);

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

	public function isColumnAutoIncremented(int|string $key): ?bool
	{
		if($this->hasColumn($key))
			return $this->columns[$key]['autoIncrement'] ?? null;

		return null;
	}
	public function setColumnAutoIncrement(int|string $key, bool $state = true): void
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
		static::reflectColumnPropertiesAttributes($reflection, $schema, $className);
		static::reflectReferencePropertiesAttributes($reflection, $schema, $className);
	}

	private static function reflectColumnPropertiesAttributes(ReflectionClass $reflection, Schema &$schema, string $className): void
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
					// should infer type in DB from type in Model. So instanciator should be known here. Or should it ?
					if($type = $propertyReflection->getType()) {
						if($types = $type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType ? $type->getTypes() : [$type]) {
							foreach($types as $type) {
								// var_dump($type->getName());

								$schema->setColumnNullable($propertyReflection->getName(), $type->allowsNull());

								if($type->isBuiltin()) { // PHP builtin types
									$schema->setColumnType(
										$propertyReflection->getName(),
										match($type->getName()) {
											'int' => 'INT',
											'bool' => 'TINYINT(1)',
											'double', 'float' => 'DOUBLE',
											'string' => 'TEXT',
											default => 'TEXT'
									});
									break;
								} else {
									$typeName = $type->getName();

									// if($typeName == 'Reflexive\Model\Collection') {
									// 	break;
									// }

									if(class_exists($typeName)) { // object
										foreach($propertyReflection->getAttributes(Reference::class) as $referenceReflection) {
											static::reflectReferencePropertiesAttribute($referenceReflection->newInstance(), $schema, $propertyReflection->getName());
										}
										if($schema->hasReference($propertyReflection->getName())) {
											$schema->setColumnType(
												$propertyReflection->getName(),
												'INT'
											);
											break;
										} elseif(enum_exists($typeName)) { // PHP enum
											$schema->setColumnType(
												$propertyReflection->getName(),
												'ENUM('.implode(',', array_map(fn($case) => $case->value, $typeName::cases())).')'
											);
											break;
										} else {
											$schema->setColumnType(
												$propertyReflection->getName(),
												match($typeName) {
													'DateTime' => 'DATETIME',
													default => ''
												}
											);
											break;
										}
									}
								}
							}
						} else {
							var_dump('NO TYPE ?');
						}
					}
				}

				if(!empty($modelAttribute->nullable))
					$schema->setColumnNullable($propertyReflection->getName(), $modelAttribute->nullable);

				if(!empty($modelAttribute->unique))
					$schema->setColumnUnique($propertyReflection->getName(), $modelAttribute->unique);
				else {
					// should infer unique from ?.
				}

				if(!empty($modelAttribute->autoIncrement)) {
					$schema->setColumnAutoIncrement($propertyReflection->getName(), $modelAttribute->autoIncrement);
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

				if(empty($modelAttribute->type))
					throw new \InvalidArgumentException('Referenced schema "'.$modelAttribute->type.'" does not exists, from schema "'.$className.'"');

				static::reflectReferencePropertiesAttribute($modelAttribute, $schema, $propertyReflection->getName());
			}
		}
	}

	private static function reflectReferencePropertiesAttribute(Reference $modelAttribute, Schema &$schema, string $propertyName): void
	{
		if($schema->hasReference($propertyName))
			return;

		$referencedSchema = self::initFromAttributes($modelAttribute->type);

		if(isset($referencedSchema)) {
			$schema->setReferenceCardinality($propertyName, $modelAttribute->cardinality);

			if(!empty($modelAttribute->nullable))
				$schema->setReferenceNullable($propertyName, $modelAttribute->nullable);
			else {
				// should infer nullable from ?.
			}

			$schema->setReferenceColumnName($propertyName,  match($modelAttribute->cardinality) {
				Cardinality::OneToOne => $modelAttribute->columnName ?? $propertyName,
				Cardinality::OneToMany => $modelAttribute->columnName ?? $propertyName,
				Cardinality::ManyToMany => $modelAttribute->columnName ?? $schema->getUIdColumnName() ?? 'id',
			});

			switch($modelAttribute->cardinality) {
				case Cardinality::OneToOne:
				case Cardinality::OneToMany:
					if(!empty($modelAttribute->foreignTableName)) {
						$schema->setReferenceForeignTableName(
							$propertyName,
							$modelAttribute->foreignTableName
						);
					}
				break;
				case Cardinality::ManyToMany:
					$schema->setReferenceForeignTableName(
						$propertyName,
						$modelAttribute->foreignTableName ?? lcfirst($schema->getTableName()).'In'.$referencedSchema->getTableName()
					);
				break;
			}

			switch($modelAttribute->cardinality) {
				case Cardinality::OneToOne:
					$schema->setReferenceForeignColumnName(
						$propertyName,
						$schema->getReferenceColumnName($propertyName)
					);
				break;
				case Cardinality::OneToMany:
					if(!empty($modelAttribute->foreignColumnName)) {
						$schema->setReferenceForeignColumnName(
							$propertyName,
							$modelAttribute->foreignColumnName
						);
					}
				break;
				case Cardinality::ManyToMany:
					$schema->setReferenceForeignColumnName(
						$propertyName,
						$modelAttribute->foreignColumnName ?? lcfirst($schema->getTableName()).ucfirst($schema->getReferenceColumnName($propertyName))
					);
				break;
			}

			if($modelAttribute->cardinality == Cardinality::ManyToMany)
				$schema->setReferenceForeignRightColumnName($propertyName, $modelAttribute->foreignRightColumnName ?? $modelAttribute->foreignColumnName ?? lcfirst($referencedSchema->getTableName()).ucfirst($schema->getReferenceColumnName($propertyName)));

			$schema->setReferenceType($propertyName, $modelAttribute->type);
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
						static::$schemas[$className] = $schema;
						break;
					} else
						throw new \InvalidArgumentException('Could not infer Schema from Model attributes. No table name.');
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

					$schema->complete();
					static::$schemas[$className] = $schema;
					return $schema;
				}
			} catch (\ReflectionException $e) {
				throw new \InvalidArgumentException('Could not infer Schema from Model attributes. Reflection failed.', previous: $e);
			}
		}

		return $schema;
	}

	public function complete(): void
	{
		$this->complete = true;
	}

	public static function getCache(): array
	{
		return self::$schemas;
	}

	public function dumpSQL(): string
	{
		$str = 'CREATE TABLE '. $this->getTableName() .' (';

		$columns = array_flip($this->columnNames);

		foreach($columns as $columnName => $propertyName) {
			$str.= '`'. $columnName .'` '. $this->getColumnType($propertyName) . ($this->isColumnNullable($propertyName)?'':' NOT NULL') . ($this->isColumnAutoIncremented($propertyName)?' AUTO_INCREMENT':'') .', ';
		}

		if($primaryColumnName = $this->getUIdColumnName())
			$str.= 'PRIMARY KEY (`'.$primaryColumnName.'`)';

		return $str.'); ';
	}

	public static function export(Event $event)
	{
		$io = $event->getIO();
		// $extra = $event->getComposer()->getPackage()->getExtra();

		$classNames = [];

		$classLoader = require('vendor/autoload.php');
		if($classLoader->isClassMapAuthoritative()) {
			$io->write('ClassMap is authoritative, using generated classMap', true, $io::VERBOSE);

			$classNames = array_keys($classLoader->getClassMap());
		} else {
			$io->write('Using composer autoload with temporary classMap', true, $io::VERBOSE);
			foreach($event->getComposer()->getPackage()->getAutoload() as $type => $autoLoad) {
				$io->write('Checking '.$type.' autoload', true, $io::VERBOSE);
				foreach($autoLoad as $nameSpace => $filePath) {
					$io->write('Checking in '.$filePath.' for nameSpace "'.$nameSpace.'"', true, $io::VERBOSE);

					$classMap = \Composer\Autoload\ClassMapGenerator::createMap($filePath);
					foreach($classMap as $className => $classPath) {
						$io->write('Loaded '.$className.' in '.$classPath, true, $io::VERY_VERBOSE);
						$classNames[] = $className;
					}
				}
			}
		}

		$io->write('Found '.count($classNames).' models', true, $io::NORMAL);

		foreach($classNames as $className) {
			$str = self::initFromAttributes($className)?->dumpSQL();

			if(!empty($str)) {
				$io->writeRaw($str, true);
				$io->writeRaw('', true);
			}
		}

		// $io->writeRaw(json_encode(self::getCache(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT), true);
	}
}
