<?php

declare(strict_types=1);

namespace Reflexive\Model;

use LogicException;
use ReflectionClass;
use ReflectionParameter;
use ReflectionUnionType;
use ReflectionIntersectionType;

use Psr\SimpleCache;
use Reflexive\Query;

class Schema implements \JsonSerializable
{
	// temporary var
	public static bool $debug = false;

	public bool $useModelNames = true;
	public bool $inheritParentColumns = true;
	protected bool $isSuperType = false;
	protected ?string $superType = null; // super type className if any
	protected array $subTypes = []; // [sub types classNames] if any

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
	protected string|array|null $uIdPropertyName = null;
	/*
	 * $references[string propertyName] = [
		 'tableName' => string,
		 'columnName' => string,
		 'type' => string,
	 ];
	 */
	protected array $references = [];
	protected bool $enum = false;

	protected bool $complete = false;

	// global caches ?
	public static bool $useInternalCache = true;
	public static ?SimpleCache\CacheInterface $cache = null;
	public static int $cacheTTL = 300;
	protected static array $schemas = [];

	// stats
	public static int $initCount = 0;

	protected static function _getSchema(string $className): ?self
	{
		return static::$schemas[$className] ?? self::$cache?->get('schema_'.$className) ?? null;
	}

	public static function getSchema(string $className): ?self
	{
		return static::_getSchema($className) ?? self::initFromAttributes($className) ?? null;
	}

	public function getSuperSchema(): ?self
	{
		return !empty($this->superType) ? self::getSchema($this->superType) : null;
	}

	protected static function _setSchema(string $className, self $schema): void
	{
		if(static::$useInternalCache)
			static::$schemas[$className] = $schema;

		self::$cache?->set('schema_'.$className, $schema, 300);
	}

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
		// unset($this->columns[$key]['columnName']);
	}

	public function getColumnNames(bool $prefixed = true): array
	{
		if($prefixed)
			return array_values(array_map(fn($value): string => $this->tableName.'.'.$value, $this->columnNames));
		else
			return array_values($this->columnNames);
	}

	public function getPropertyNames(): array
	{
		return array_keys($this->columnNames);
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

	public function getColumnExtra(int|string $key): ?Query\ColumnExtra
	{
		if($this->hasColumn($key))
			return $this->columns[$key]['extra'];

		return null;
	}
	public function setColumnExtra(int|string $key, ?Query\ColumnExtra $extra): void
	{
		if(null === $extra) {
			if($this->hasColumnExtra($key))
				unset($this->columns[$key]['extra']);

			return;
		}

		if($this->hasColumn($key)) {
			$this->columns[$key]['extra'] = $extra;
		} else {
			$this->columns[$key] = ['extra' => $extra];
		}
	}
	public function hasColumnExtra(int|string $key): bool
	{
		return isset($this->columns[$key]['extra']);
	}

	public function getUIdColumnName(): array|null
	{

		if(empty($this->uIdPropertyName))
			return null;

		if(is_array($this->uIdPropertyName))
			return array_map(fn($propertyName): ?string => $this->getColumnName($propertyName) ?? $this->getReferenceColumnName($propertyName), $this->uIdPropertyName);
		else {
			return [$this->getColumnName($this->uIdPropertyName) ?? $this->getReferenceColumnName($this->uIdPropertyName)];
		}
	}
	public function getUIdColumnNameString(): ?string
	{
		$name = $this->getUIdColumnName();
		if(is_array($name))
			return implode(', ', $name);

		return $name;
	}
	public function getUIdPropertyName(): string|array|null
	{
		return $this->uIdPropertyName;
	}
	public function setUIdPropertyName(string ...$name): void
	{
		if(count($name) == 1)
			$name = $name[0];

		$this->uIdPropertyName = $name;
	}
	public function addUIdPropertyName(string ...$name): void
	{
		if(empty($this->uIdPropertyName))
			$this->uIdPropertyName = [];

		$this->uIdPropertyName += array_merge($this->uIdPropertyName, $name);
	}

	public function getModelId(Model $model): int|string|array
	{
		$propertyNames = $this->getUIdPropertyName();
		if(empty($propertyNames))
			return -1;

		$classReflection = new ReflectionClass($model::class);
		if(is_array($propertyNames)) {
			$id = [];
			foreach($propertyNames as $propertyName) {
				$id[$propertyName] = $classReflection->getProperty($propertyName)->getValue($model);
			}

			return $id;
		}

		return $classReflection->getProperty($propertyNames);
	}
	public function getModelIdString(Model $model): int|string|array
	{
		$id = $this->getModelId($model);
		if(is_array($id)) {
			$str = '';
			foreach($id as $value) {
				if(is_object($value) && enum_exists($value::class))
					$str.= $value->name;
				else
					$str.= $value;
				$str.= ', ';
			}
			return rtrim($str, ', ');
		}

		return $id;
	}
	public function setModelId(Model $model, string $propertyName, int|string $value): void
	{
		$classReflection = new ReflectionClass($model::class);
		$classReflection?->getProperty($propertyName)?->setValue($model, $value);
	}

	public function hasUId(): bool
	{
		if(!isset($this->uIdPropertyName))
			return false;

		if(is_array($this->uIdPropertyName)) {
			foreach($this->uIdPropertyName as $propertyName) {
				if(!$this->hasColumn($propertyName))
					return false;
			}
			return true;
		} else
			return $this->hasColumn($this->uIdPropertyName);

	}

	public function getUIdColumnType(): string|array|null
	{
		if(is_array($this->uIdPropertyName)) {
			$fn = fn($propertyName): array|null|string => $this->getColumnTypeString($propertyName);

			return array_map(function($item) use($fn){
				return is_array($item) ? array_map($fn, $item) : $fn($item);
			}, $this->uIdPropertyName);
		} elseif(null !== $this->uIdPropertyName)
			return $this->getColumnTypeString($this->uIdPropertyName);

		return null;
	}
	public function getUIdColumnTypeString(): ?string
	{
		$type = $this->getUIdColumnType();
		if(is_array($type))
			return implode(', ', $type);

		return $type;
	}

	public function hasReference(int|string $key): bool
	{
		return isset($this->references[$key]);
	}
	public function hasReferences(): bool
	{
		return !empty($this->references);
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
			return $this->references[$key]['foreignTableName'] ?? null;

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

	// public function isReferenceLazy(int|string $key): ?bool
	// {
	// 	if($this->hasReference($key))
	// 		return $this->references[$key]['lazy'] ?? null;
//
	// 	return null;
	// }
	// public function setReferenceLazy(int|string $key, bool $lazy = true): void
	// {
	// 	if($this->hasReference($key))
	// 		$this->references[$key]['lazy'] = $lazy;
	// 	else
	// 		$this->references[$key] = ['lazy' => $lazy];
	// }


// 	public function isReferenceInverse(int|string $key): ?bool
// 	{
// 		if($this->hasReference($key))
// 			return $this->references[$key]['inverse'] ?? null;
//
// 		return null;
// 	}
// 	public function setReferenceInverse(int|string $key, bool $inverse = true): void
// 	{
// 		if($this->hasReference($key))
// 			$this->references[$key]['inverse'] = $inverse;
// 		else
// 			$this->references[$key] = ['inverse' => $inverse];
// 	}

	public function getColumnType(int|string $key): string|array|null
	{
		if($this->hasColumn($key))
			return $this->columns[$key]['type'] ?? null;
		elseif($this->hasReference($key)) {
			return self::initFromAttributes($this->references[$key]['type'])->getUIdColumnType();
		}

		return null;
	}
	public function getColumnTypeString(int|string $key): ?string
	{
		$types = $this->getColumnType($key);
		if(is_array($types))
			return implode(', ', $types);

		return $types;
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
			return $this->columns[$key]['nullable'] ?? false;

		return null;
	}
	public function setColumnNullable(int|string $key, bool $nullable = true): void
	{
		if($this->hasColumn($key)) {
			$this->columns[$key]['nullable'] = $nullable;
		} else {
			$this->columns[$key] = ['nullable' => $nullable];
		}
	}

	public function hasColumnDefaultValue(int|string $key): ?bool
	{
		if($this->hasColumn($key))
			return isset($this->columns[$key]['defaultValue']);

		return null;
	}
	public function getColumnDefaultValue(int|string $key): mixed
	{
		if($this->hasColumn($key))
			return $this->columns[$key]['defaultValue'] ?? null;

		return null;
	}
	public function setColumnDefaultValue(int|string $key, mixed $defaultValue): void
	{
		if($this->hasColumn($key)) {
			$this->columns[$key]['defaultValue'] = $defaultValue;
		} else {
			$this->columns[$key] = ['defaultValue' => $defaultValue];
		}
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
			$this->columns[$key] = ['unique' => $unique];
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

	public function setEnum(bool $state = true): void
	{
		$this->enum = $state;
	}
	public function isEnum(): bool
	{
		return $this->enum;
	}

	public function isComplete(): bool
	{
		return $this->complete;
	}

	public function isSuperType(): bool
	{
		return $this->isSuperType;
	}
	public function addSubType(string $subType): void
	{
		$this->subTypes[$subType] = '';
		static::write(' ✓ added subType '. $subType, 36);
	}
	public function getSubTypes(): array
	{
		return array_keys($this->subTypes);
	}

	public function isSubTypeOf(string $className): bool
	{
		return $this->superType === $className;
	}

	public function isSubType(): bool
	{
		return !empty($this->superType);
	}

	public function getSuperType(): ?string
	{
		return $this->superType;
	}

	public function __toString()
	{
		return json_encode($this, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
	}

	public function jsonSerialize(): mixed
	{
		return [
			'complete' => $this->complete,
			'isEnum' => $this->enum,
			'tableName' => $this->tableName,
			'uIdPropertyName' => $this->uIdPropertyName,
			'columns' => $this->columns,
			'references' => $this->references,
			'superType' => $this->superType,
		];
		// $array['references']??= $this->references;
	}

	private static function reflectPropertiesAttributes(ReflectionClass $reflection, Schema &$schema, string $className): void
	{
		static::reflectColumnPropertiesAttributes($reflection, $schema, $className);
		static::reflectReferencePropertiesAttributes($reflection, $schema, $className);
	}

	private static function reflectColumnPropertiesAttributes(ReflectionClass $reflection, Schema &$schema, string $className): void
	{
		foreach($reflection->getProperties() as $propertyReflection) {
			if(!$schema->inheritParentColumns && $propertyReflection->class !== $className)
				continue;

			foreach($propertyReflection->getAttributes(Column::class) as $attributeReflection) {
				if($schema->hasColumn($propertyReflection->getName()))
					continue;

				$modelAttribute = $attributeReflection->newInstance();

				if(!empty($modelAttribute->name))
					$schema->setColumnName($propertyReflection->getName(), $modelAttribute->name);
				elseif($schema->useModelNames)
					$schema->setColumnName($propertyReflection->getName(), $propertyReflection->getName());
				else
					throw new LogicException('Unamed column');

				if(!empty($modelAttribute->type))
					$schema->setColumnType($propertyReflection->getName(), $modelAttribute->type);
				else {
					// should infer type in DB from type in Model. So instanciator should be known here. Or should it ?
					if($type = $propertyReflection->getType()) {
						if($types = $type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType ? $type->getTypes() : [$type]) {
							foreach($types as $type) {
								$schema->setColumnNullable($propertyReflection->getName(), $type->allowsNull());

								if(!empty($modelAttribute->defaultValue))
									$schema->setColumnDefaultValue($propertyReflection->getName(), $modelAttribute->defaultValue);
								elseif($propertyReflection->isPromoted()) {
									try {
										$parameterReflection = new ReflectionParameter([$className, '__construct'], $propertyReflection->getName());

										$defaultValue = $parameterReflection->getDefaultValue();
									} catch (\ReflectionException) {
										if(false !== $parentClassReflection = $reflection->getParentClass()) {
											try {
												$parameterReflection = new ReflectionParameter([$parentClassReflection->getName(), '__construct'], $propertyReflection->getName());

												$defaultValue = $parameterReflection->getDefaultValue();
											} catch (\ReflectionException) {
												$defaultValue = null;
											}
										}
									} finally {
										if(isset($defaultValue))
											$schema->setColumnDefaultValue($propertyReflection->getName(), $defaultValue);
									}

								} elseif($propertyReflection->hasDefaultValue())
									$schema->setColumnDefaultValue($propertyReflection->getName(), $propertyReflection->getDefaultValue());

								if($type->isBuiltin()) { // PHP builtin types
									$className::initModelAttributes();
									$maxLength = Model::getPropertyMaxLength($className, $propertyReflection->getName());

									$schema->setColumnType(
										$propertyReflection->getName(),
										match($type->getName()) {
											'int' => $maxLength ? 'INT('.$maxLength.')' :'INT',
											'bool' => 'TINYINT(1)',
											'double', 'float' => 'DOUBLE',
											'string' => $maxLength ? 'VARCHAR('.$maxLength.')' :'TEXT',
											default => 'TEXT'
									});
									break;
								} else {
									/** @psalm-var class-string $typeName */
									$typeName = $type->getName();

									// if($typeName == 'Reflexive\Model\Collection') {
									// 	break;
									// }

									if(class_exists($typeName)) { // object
										// foreach($propertyReflection->getAttributes(Reference::class) as $referenceReflection) {
										// 	static::reflectReferencePropertiesAttribute($referenceReflection->newInstance(), $schema, $className, $propertyReflection->getName());
										// }
										// if($schema->hasReference($propertyReflection->getName())) {
										// 	$schema->setColumnType(
										// 		$propertyReflection->getName(),
										// 		'BIGINT'
										// 	);
										// 	break;
										// } else
										if(enum_exists($typeName)) { // PHP enum
											$schema->setColumnType(
												$propertyReflection->getName(),
												'ENUM('.implode(',', array_map(fn($case) => '\''.$case->name.'\'', $typeName::cases())).')'
											);
											if($propertyReflection->hasDefaultValue() && !$schema->hasColumnDefaultValue($propertyReflection->getName())) {
												$schema->setColumnDefaultValue(
													$propertyReflection->getName(), $propertyReflection->getDefaultValue()?->name
												);
											}
											break;
										} else {
											$schema->setColumnType(
												$propertyReflection->getName(),
												match($typeName) {
													\DateTime::class => 'DATETIME',
													default => ''
												}
											);
											break;
										}
									}
								}
							}
						} else {
							throw new LogicException('NO TYPE ?');
						}
					} else {
						throw new LogicException('NO TYPE ?');
					}
				}

				if(!empty($modelAttribute->nullable))
					$schema->setColumnNullable($propertyReflection->getName(), $modelAttribute->nullable);

				//if(!empty($modelAttribute->unique)) // not nullable
					$schema->setColumnUnique($propertyReflection->getName(), $modelAttribute->unique);
				//else {
					// should infer unique from ?.
				//}

				//if(!empty($modelAttribute->autoIncrement)) { // not nullable
					$schema->setColumnAutoIncrement($propertyReflection->getName(), $modelAttribute->autoIncrement);
				//}

				if($modelAttribute->isId)
					$schema->addUIdPropertyName($propertyReflection->getName());

				if(!empty($modelAttribute->extra))
					$schema->setColumnExtra($propertyReflection->getName(), $modelAttribute->extra);
			}
		}
	}

	private static function reflectReferencePropertiesAttributes(ReflectionClass $reflection, Schema &$schema, string $className): void
	{
		foreach($reflection->getProperties() + $reflection->getReflectionConstants() as $propertyReflection) {
			if(!$schema->inheritParentColumns && $propertyReflection->class !== $className)
				continue;

			foreach($propertyReflection->getAttributes(Reference::class) as $attributeReflection) {
				$modelAttribute = $attributeReflection->newInstance();

				// if($modelAttribute->type == $className)
				// 	continue;

				if(empty($modelAttribute->type))
					throw new \InvalidArgumentException('Referenced schema "'.$modelAttribute->type.'" does not exists, from schema "'.$className.'"');

				static::reflectReferencePropertiesAttribute($modelAttribute, $schema, $className, $propertyReflection->getName());
			}
		}
	}

	private static function reflectReferencePropertiesAttribute(Reference $modelAttribute, Schema &$schema, string $className, string $propertyName): void
	{
		if($schema->hasReference($propertyName))
			return;

		$referencedSchema = self::initFromAttributes($modelAttribute->type);

		if(isset($referencedSchema)) {
			if(!$referencedSchema->hasUId())
				throw new \InvalidArgumentException('Cannot reference schema "'.$modelAttribute->type.'" without its uId, from schema "'.$className.'::'.$propertyName.'"');

			$schema->setReferenceCardinality($propertyName, $modelAttribute->cardinality);

			if(!empty($modelAttribute->nullable)) {
				$schema->setReferenceNullable($propertyName, $modelAttribute->nullable);
			}

			// if(null !== $modelAttribute->inverse)
			// 	$schema->setReferenceInverse($propertyName, $modelAttribute->inverse);

			$schema->setReferenceColumnName($propertyName,  match($modelAttribute->cardinality) {
				Cardinality::OneToOne => $modelAttribute->columnName ?? $propertyName,
				Cardinality::OneToMany => $modelAttribute->columnName ?? $propertyName,
				Cardinality::ManyToOne => lcfirst($className) ?? $propertyName,
				Cardinality::ManyToMany => $modelAttribute->columnName ?? $schema->getUIdColumnNameString() ?? 'id',
			});

			switch($modelAttribute->cardinality) {
				case Cardinality::OneToOne:
					$schema->setReferenceForeignTableName(
						$propertyName,
						$modelAttribute->foreignTableName ?? $referencedSchema->getTableName()
					);
					$schema->setReferenceForeignColumnName(
						$propertyName,
						$schema->getReferenceColumnName($propertyName)
					);
				break;
				case Cardinality::OneToMany:
					if(!empty($modelAttribute->foreignTableName)) {
						$schema->setReferenceForeignTableName(
							$propertyName,
							$modelAttribute->foreignTableName
						);
					}
					if(!empty($modelAttribute->foreignColumnName)) {
						$schema->setReferenceForeignColumnName(
							$propertyName,
							$modelAttribute->foreignColumnName
						);
					}
				break;
				case Cardinality::ManyToOne:
					if(!empty($modelAttribute->foreignTableName)) {
						$schema->setReferenceForeignTableName(
							$propertyName,
							$modelAttribute->foreignTableName
						);
					}
					if(!empty($modelAttribute->foreignColumnName)) {
						$schema->setReferenceForeignColumnName(
							$propertyName,
							$modelAttribute->foreignColumnName
						);
					}
				break;
				case Cardinality::ManyToMany:
					$schema->setReferenceForeignTableName(
						$propertyName,
						$modelAttribute->foreignTableName ?? lcfirst($schema->getTableName()).'Have'.$referencedSchema->getTableName()
					);
					$schema->setReferenceForeignColumnName(
						$propertyName,
						$modelAttribute->foreignColumnName ?? lcfirst($schema->getTableName()).ucfirst($schema->getReferenceColumnName($propertyName))
					);
					$schema->setReferenceForeignRightColumnName($propertyName, $modelAttribute->foreignRightColumnName ?? $modelAttribute->foreignColumnName ?? lcfirst($referencedSchema->getTableName()).ucfirst($schema->getReferenceColumnName($propertyName)));
				break;
			}

			$schema->setReferenceType($propertyName, $modelAttribute->type);
		}
	}

// 	private static function reflectRelationPropertiesAttributes(ReflectionClass $reflection, Schema &$schema, string $className): void
// 	{
// 		foreach($reflection->getProperties() + $reflection->getReflectionConstants() as $propertyReflection) {
// 			if(!$schema->inheritParentColumns && $propertyReflection->class !== $className)
// 				continue;
//
// 			foreach($propertyReflection->getAttributes(Relation::class) as $attributeReflection) {
// 				$modelAttribute = $attributeReflection->newInstance();
//
// 				// if($modelAttribute->type == $className)
// 				// 	continue;
//
// 				static::reflectRelationPropertiesAttribute($modelAttribute, $schema, $className, $propertyReflection->getName());
// 			}
// 		}
// 	}
//
// 	private static function reflectRelationPropertiesAttribute(Reference $modelAttribute, Schema &$schema, string $className, string $propertyName): void
// 	{
// 		if($schema->hasRelation($propertyName))
// 			return;
//
// 		$schema->setRelationNullable($propertyName, $modelAttribute->nullable);
//
// 		$schema->setRelationColumnName($propertyName, $modelAttribute->columnName);
//
// 		$schema->setRelationType($propertyName, $modelAttribute->type);
// 	}

	private static function reflectColumnMethodsAttributes(ReflectionClass $reflection, Schema &$schema, string $className): void
	{
		foreach($reflection->getMethods() as $methodReflection) {
			if(!$schema->inheritParentColumns && $methodReflection->class !== $className)
				continue;

			foreach($methodReflection->getAttributes(Column::class) as $attributeReflection) {
				if($schema->hasColumn($methodReflection->getName()))
					continue;

				$methodAttribute = $attributeReflection->newInstance();

				if(!empty($methodAttribute->name))
					$schema->setColumnName($methodReflection->getName(), $methodAttribute->name);
				elseif($schema->useModelNames)
					$schema->setColumnName($methodReflection->getName(), $methodReflection->getName());

				if(!empty($methodAttribute->type))
					$schema->setColumnType($methodReflection->getName(), $methodAttribute->type);
				else {
					// should infer type in DB from type in Model. So instanciator should be known here. Or should it ?
					if($type = $methodReflection->getReturnType()) {
						if($types = $type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType ? $type->getTypes() : [$type]) {
							foreach($types as $type) {
								$schema->setColumnNullable($methodReflection->getName(), $type->allowsNull());

								if(!empty($methodAttribute->defaultValue))
									$schema->setColumnDefaultValue($methodReflection->getName(), $methodAttribute->defaultValue);

								if($type->isBuiltin()) { // PHP builtin types
									if(method_exists($className, 'initModelAttributes'))
										$className::initModelAttributes();
									$maxLength = Model::getPropertyMaxLength($className, $methodReflection->getName());

									$schema->setColumnType(
										$methodReflection->getName(),
										match($type->getName()) {
											'int' => $maxLength ? 'INT('.$maxLength.')' :'INT',
											'bool' => 'TINYINT(1)',
											'double', 'float' => 'DOUBLE',
											'string' => $maxLength ? 'VARCHAR('.$maxLength.')' :'TEXT',
											default => 'TEXT'
									});
									break;
								} else {
									/** @psalm-var class-string $typeName */
									$typeName = $type->getName();

									if(class_exists($typeName)) { // object
										if(enum_exists($typeName)) { // PHP enum
											$schema->setColumnType(
												$methodReflection->getName(),
												'ENUM('.implode(',', array_map(fn($case) => '\''.$case->name.'\'', $typeName::cases())).')'
											);
											break;
										} else {
											$schema->setColumnType(
												$methodReflection->getName(),
												match($typeName) {
													\DateTime::class => 'DATETIME',
													default => ''
												}
											);
											break;
										}
									}
								}
							}
						} else {
							throw new \LogicException('NO TYPE ?');
						}
					} else {
						throw new \LogicException('NO TYPE ?');
					}
				}

				if(isset($methodAttribute->nullable))
					$schema->setColumnNullable($methodReflection->getName(), $methodAttribute->nullable);

				$schema->setColumnUnique($methodReflection->getName(), $methodAttribute->unique);

				$schema->setColumnAutoIncrement($methodReflection->getName(), $methodAttribute->autoIncrement);

				if($methodAttribute->isId) {
					$schema->addUIdPropertyName($methodReflection->getName());
				}
			}
		}
	}

	public static function write(string $message, int $colorCode = 0): void
	{
		// echo str_repeat(' ', static::$indent), "\e[1;", $colorCode, "m", $message, "\e[0m", PHP_EOL;
	}
	public static int $indent = 0;
	public static function indent(): void
	{
		static::$indent++;
	}
	public static function deindent(): void
	{
		static::$indent--;

		if(static::$indent < 0)
			static::$indent = 0;
	}


	public static function initFromAttributes(string $className): ?static
	{
		$schema = static::_getSchema($className) ?? null;

		if(!isset($schema)/* || !$schema->isComplete()*/) {

			try {
				$classReflection = new ReflectionClass($className);
				$useModelNames = true;
				$subTypeNames = [];

				// get attributes of class : Table
				foreach($classReflection->getAttributes(Table::class) as $attributeReflection) {
					$attribute =  $attributeReflection->newInstance();
					if(!empty($attribute->tableName) || $useModelNames) {
						$schema = new static($attribute->tableName ?? $className);
						static::write(' -> will init schema '. $schema->getTableName(), 33);
						static::indent();
						static::_setSchema($className, $schema);

						$schema->inheritParentColumns = $attribute->inheritColumns ?? true;
						$schema->useModelNames = $attribute->useModelNames;

						$schema->isSuperType = $attribute->isSuperType;
						$subTypeNames = $attribute->subTypes; // TEMPORARY

						if($attribute->isSubType) {
							$parentClassReflection = $classReflection->getParentClass();

							if(false !== $parentClassReflection) {
								if($parentClassReflection->getName() != 'Reflexive\Model\Model') {
									$schema->superType = $parentClassReflection->getName();

									if(null === $attribute->inheritColumns)
										$schema->inheritParentColumns = false;
								} else {
									throw new \InvalidArgumentException('Model set #[Table(isSubType: true)] but class only have Reflexive\Model\Model as parent.');
								}
							} else {
								throw new \InvalidArgumentException('Model set #[Table(isSubType: true)] but class does not have a parent.');
							}
						}

						break;
					} else
						throw new \InvalidArgumentException('Could not infer Schema from Model attributes. No table name.');

				}

				if(isset($schema)) {
					if($classReflection->isEnum()) { // is enum
						$schema->setEnum(true);
						// $enumReflection = new ReflectionEnum($className);
						$schema->setColumnName('id', 'id');
						$schema->setUIdPropertyName('id');

						// $cases = $className::cases();
						// $type = $enumReflection->getBackingType();
						// if(!$type || $type instanceof ReflectionNamedType && $type->getName() == 'string') {
							$schema->setColumnType('id', 'VARCHAR('.max(array_map(fn ($case) => strlen($case->name), $className::cases())).')');
						// } else {
						// 	$values = array_map(fn ($case) => $case->value, $cases);
						// 	$schema->setColumnType('id', self::dbIntegerSize(min($values), max($values)));
						// }
					}

					// get attributes of traits properties
					foreach($classReflection->getTraits() as $traitReflection) {
						static::reflectPropertiesAttributes($traitReflection, $schema, $className);
					}

					// get attributes of properties
					static::reflectPropertiesAttributes($classReflection, $schema, $className);

					static::reflectColumnMethodsAttributes($classReflection, $schema, $className);

					foreach(array_keys($schema->getReferences()) as $key) {
						if($schema->hasColumn($key)) {
							$schema->setReferenceColumnName($key, $schema->getColumnName($key));

							if(is_null($schema->isReferenceNullable($key)))
								$schema->setReferenceNullable($key, $schema->isColumnNullable($key));

// 							if($schema->isReferenceInverse($key)) {
// 								$columnName = $schema->getColumnName($key);
// 								$schema->setReferenceColumnName($columnName, $columnName);
//
// 								$schema->setReferenceType($columnName, $className);
// 								$schema->setReferenceCardinality($columnName, $schema->getReferenceCardinality($key));
// 								$schema->setReferenceNullable($columnName, $schema->isReferenceNullable($key));
// 							}

							$schema->unsetColumn($key);
						}
					}

					static::_setSchema($className, $schema);

					if($schema->isSuperType) {

						if(empty($subTypeNames)) {
							// <WARNING, TEMPORARY : ressource intensive : will scan models to init schema of each subTypes>
							// <temporary Composer dependancy…>
							$otherClassNames = [];

							$classLoader = null;
							if(!isset($GLOBALS['__composer_autoload_files'])) {
								$classLoader = require('vendor/autoload.php');
							} else {
								$iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator(spl_autoload_functions()), \RecursiveIteratorIterator::CHILD_FIRST);
								foreach($iterator as $v) {
									if($v instanceof \Composer\Autoload\ClassLoader) {
										$classLoader = $v;

										break;
									}
								}
							}

							if(isset($classLoader)) {
								if($classLoader->isClassMapAuthoritative()) {
									// ClassMap is authoritative, using composer classMap
									$otherClassNames = array_keys($classLoader->getClassMap());
								} else {
									// Using composer autoload with temporary classMap
									foreach($classLoader->getFallbackDirsPsr4() as $filePath) {
										$classMap = \Composer\Autoload\ClassMapGenerator::createMap($filePath);
										$otherClassNames += array_keys($classMap);
									}
									// var_dump($otherClassNames);
								}
								// </ temporary Composer dependancy…>

								foreach($otherClassNames as $otherClassName) {
									if(class_exists($otherClassName, true)) {
										$potentialSubClassReflection = new ReflectionClass($otherClassName);
										if(!$potentialSubClassReflection->isAbstract() && $potentialSubClassReflection->isSubclassOf($className)) {
											$potentialSubClassSchema = static::getSchema($otherClassName);

											static::write(' ? may add subType '. $potentialSubClassReflection->getName(), 34);
											if(isset($potentialSubClassSchema) && $potentialSubClassSchema->isSubTypeOf($className)) {
												static::write(' +> should add subType '. $potentialSubClassReflection->getName(), 35);
												$schema->addSubType($potentialSubClassReflection->getName());
											}
										}
									}
								}
							}
						} else {
							foreach($subTypeNames as $subTypeName) { //TEMPORARY TOO
								static::write(' +> should add subType '. $subTypeName, 35);
								$schema->addSubType($subTypeName);
							}
						}
					}

					$subTypes = $schema->getSubTypes();
					if(!empty($subTypes)) {
						$schema->setColumnName('reflexive_subType', 'reflexive_subType');

						$subTypesEnumString = 'ENUM(';
						foreach($schema->getSubTypes() as $subTypeClassName) {
							$subTypesEnumString.= '\''.$subTypeClassName.'\',';
						}
						$subTypesEnumString = rtrim($subTypesEnumString, ',').')';

						$schema->setColumnType('reflexive_subType', $subTypesEnumString);
					}

					if(!empty($schema->superType)) { // means we are in a subType
						$superSchema = self::getSchema($schema->superType);

						if(isset($superSchema)) { //  && $superSchema->isComplete()
							$superSchema->addSubType($className);

							static::write(' +✓ addToSuper '. $className, 37);

							static::_setSchema($schema->superType, $superSchema);

							foreach($superSchema->getUIdPropertyName() as $propertyName) {
								$schema->addUIdPropertyName($propertyName);
//
								$schema->setColumnName($propertyName, $superSchema->getColumnName($propertyName));
								$schema->setColumnType($propertyName, $superSchema->getColumnType($propertyName));
//
// 								if($superSchema->hasColumnDefaultValue($propertyName))
// 									$schema->setColumnDefaultValue($propertyName, $superSchema->getColumnDefaultValue($propertyName));
//
								$schema->setColumnUnique($propertyName, $superSchema->isColumnUnique($propertyName));
								$schema->setColumnNullable($propertyName, false);

								$schema->setReferenceColumnName($propertyName, $superSchema->getColumnName($propertyName));
								$schema->setReferenceCardinality($propertyName, Cardinality::OneToOne);
								$schema->setReferenceNullable($propertyName, false);
								$schema->setReferenceForeignTableName(
									$propertyName,
									$superSchema->getTableName()
								);
								$schema->setReferenceForeignColumnName(
									$propertyName,
									$superSchema->getColumnName($propertyName)
								);
								$schema->setReferenceType($propertyName, $schema->superType);
							}
						} else {
							throw new \LogicException('NO SUPER SCHEMA ?');
						}
					}

					$schema->complete();
					static::$initCount++;

					static::_setSchema($className, $schema);

					return $schema;
				}
			} catch (\ReflectionException $e) {
				throw new \InvalidArgumentException('Could not infer Schema from Model "'.$className.'" attributes. Reflection failed.', previous: $e);
			}
		}

		return $schema;
	}

	public function complete(): void
	{
		static::deindent();
		static::write(' ✓ completed schema '. $this->tableName, 32);
		$this->complete = true;
	}

	public static function getCache(): array
	{
		return self::$schemas;
	}

	public function dumpSQLTable(string $className): string
	{
		$query = new \Reflexive\Query\CreateTable($this->getTableName());

		foreach($this->columnNames as $propertyName => $columnName) {
			$query->addColumn(
				name: $columnName,
				type: $this->getColumnTypeString($propertyName),
				nullable: $this->isColumnNullable($propertyName) ?? $this->isReferenceNullable($propertyName),
				defaultValue: $this->getColumnDefaultValue($propertyName),
				extra: $this->isColumnAutoIncremented($propertyName) ? Query\ColumnExtra::autoIncrement : ($this->hasColumnExtra($propertyName) ? $this->getColumnExtra($propertyName) : null)
			);
		}

		foreach($this->getUIdColumnName() as $columnName) {
			$query->setPrimary($columnName);
		}

		foreach(array_keys($this->references) as $propertyName) {
			if($this->getReferenceCardinality($propertyName) === Cardinality::ManyToMany)
				continue;

			if($this->getReferenceType($propertyName) == $className)
				$referencedSchema = $this;
			else
				$referencedSchema = self::initFromAttributes($this->getReferenceType($propertyName));
			if(!$referencedSchema)
				continue;

			$query->addConstraint(
				name: $this->getTableName() .'_'. $this->getReferenceColumnName($propertyName),
				key: $this->getReferenceColumnName($propertyName),
				referencedTableName: $referencedSchema->getTableName(),
				referencedKey: $referencedSchema->getUIdColumnNameString(),
				onDelete: ($this->isReferenceNullable($propertyName)? Query\ConstraintAction::setNull : ($this->getReferenceType($propertyName) == $className ? Query\ConstraintAction::restrict : Query\ConstraintAction::cascade)),
				onUpdate: Query\ConstraintAction::cascade
			);
		}

		return $query.' ' .self::dumpEnumModelsValuesSQL($className);
	}

	public function dumpReferencesSQL(): array
	{
		$array = [];

		foreach(array_keys($this->references) as $propertyName) {
			if($dump = $this->dumpReferenceSQL($propertyName))
				$array[$propertyName] = $dump;
		}

		return $array;
	}

	private function dumpReferenceSQL(string $propertyName): ?string
	{
		if(!$this->hasReference($propertyName))
			return null;

		if($this->getReferenceCardinality($propertyName) !== Cardinality::ManyToMany)
			return null;

		$referencedSchema = self::initFromAttributes($this->getReferenceType($propertyName));
		if(!$referencedSchema)
			return null;

		$query = new Query\CreateTable($this->getReferenceForeignTableName($propertyName));

		$query->addColumn(
			name: $this->getReferenceForeignColumnName($propertyName),
			type: $this->getUIdColumnTypeString(),
			isPrimary: true
		);
		$query->addColumn(
			name: $this->getReferenceForeignRightColumnName($propertyName),
			type: $referencedSchema->getUIdColumnTypeString(),
			isPrimary: true
		);

		$query->addConstraint(
			name: $this->getReferenceForeignTableName($propertyName) .'_'. $this->getReferenceForeignColumnName($propertyName),
			key: $this->getReferenceForeignColumnName($propertyName),
			referencedTableName: $this->getTableName(),
			referencedKey: $this->getUIdColumnNameString(),
			onDelete: Query\ConstraintAction::cascade,
			onUpdate: Query\ConstraintAction::cascade
		);
		$query->addConstraint(
			name: $this->getReferenceForeignTableName($propertyName) .'_'. $this->getReferenceForeignRightColumnName($propertyName),
			key: $this->getReferenceForeignRightColumnName($propertyName),
			referencedTableName: $referencedSchema->getTableName(),
			referencedKey: $referencedSchema->getUIdColumnNameString(),
			onDelete: Query\ConstraintAction::cascade,
			onUpdate: Query\ConstraintAction::cascade
		);

		return $query.'';
	}

	public static function dumpEnumModelsValuesSQL(string $className): ?string
	{
		$schema = self::initFromAttributes($className);
		if(empty($schema) || !$schema->isEnum())
			return null;

		$str = 'INSERT INTO `'. $schema->getTableName() .'` (';
		foreach($schema->getColumnNames(false) as $columnName) {
			$str.= '`'.$columnName.'`, ';
		}
		$str = rtrim($str, ', ').') VALUES ';

		foreach($className::cases() as $case) {
			$str.= '(';
			foreach($schema->getPropertyNames() as $propertyName) {
				if($schema->getUIdPropertyName() === $propertyName) { // id
					$str.= self::quoteDbValue($case->name, $schema->getColumnType($propertyName));
				} else {
					$str.= self::quoteDbValue($case->{$propertyName}(), $schema->getColumnType($propertyName));
				}

				$str.= ', ';
			}
			$str = rtrim($str, ', ').'), ';
		}

		return rtrim($str, ', ').'; ';
	}

	private static function debug(string $messages): void
	{
		if(static::$debug)
			echo $messages, PHP_EOL;
	}
	private static function normal(string $messages): void
	{
		if(static::$debug)
			echo $messages, PHP_EOL;
	}
	private static function quiet(string $messages): void
	{
		if(static::$debug)
			echo $messages, PHP_EOL;
	}
	private static function verbose(string $messages): void
	{
		if(static::$debug)
			echo $messages, PHP_EOL;
	}
	private static function veryVerbose(string $messages): void
	{
		if(static::$debug)
			echo $messages, PHP_EOL;
	}

	public static function dumpSQL(): string
	{
		$str = '';
		// <temporary Composer dependancy…>
		$classNames = [];

		$classLoader = require('vendor/autoload.php');
		if($classLoader->isClassMapAuthoritative()) {
			// ClassMap is authoritative, using composer classMap
			$classNames = array_keys($classLoader->getClassMap());
		} else {
			// Using composer autoload with temporary classMap
			foreach($classLoader->getFallbackDirsPsr4() as $filePath) {
				$classMap = \Composer\Autoload\ClassMapGenerator::createMap($filePath);
				$classNames += array_keys($classMap);
			}
		}
		// </ temporary Composer dependancy…>

		$str.= PHP_EOL;
		$str.= '-- Begining of export --'. PHP_EOL;
		$str.= '-- Ignore foreign keys checks while creating tables.'. PHP_EOL;
		$str.= 'SET foreign_key_checks = 0;'. PHP_EOL;
		$str.= PHP_EOL;

		$str.= '-- Creating entities tables.'. PHP_EOL;
		$count = 0;
		foreach($classNames as $className) {
			$classReflection = new ReflectionClass($className);

			$schema = self::getSchema($className);
			if(!isset($schema) || ($classReflection->isAbstract() && !$schema->isSuperType()))
				continue;

			$str.= $schema->dumpSQLTable($className). PHP_EOL;
			$str.= PHP_EOL;
			$count++;
		}
		$str.= '-- Created '.$count.' entities'. PHP_EOL;
		$str.= PHP_EOL;

		$str.= '-- Creating associations tables.'. PHP_EOL;
		$count = 0;
		foreach($classNames as $className) {
			$classReflection = new ReflectionClass($className);

			$schema = self::getSchema($className);
			if($classReflection->isAbstract() && !$schema?->isSuperType())
				continue;

			foreach(self::initFromAttributes($className)?->dumpReferencesSQL() ?? [] as $dump) {
				$str.= $dump. PHP_EOL;
				$str.= PHP_EOL;
				$count++;
			}
		}
		$str.= '-- Created '.$count.' associations'. PHP_EOL;
		$str.= PHP_EOL;

		$str.= PHP_EOL;
		$str.= '-- Do not ignore foreign keys checks anymore.'. PHP_EOL;
		$str.= 'SET foreign_key_checks = 1;'. PHP_EOL;
		$str.= PHP_EOL;
		$str.= '-- End of export --'. PHP_EOL;

		return $str;
	}

	public static function exportSQL(): void
	{
		echo self::dumpSQL();
	}

	private static function dbIntegerSize(int $min, int $max): string
	{
		$intSizes = [
			'TINYINT' => 255,
			'SMALLINT' => 65535,
			'MEDIUMINT' => 16777215,
			'INT' => 4294967295,
			'BIGINT' => 18446744073709551615,
		];
		foreach($intSizes as $typeName => $maxValue) {
			if($min >= 0 && $max <= $maxValue) {
				return $typeName.' UNSIGNED';
			} elseif($min >= -ceil($maxValue/2) && $max <= floor($maxValue/2))
				return $typeName;
		}

		throw new \InvalidArgumentException('Integer overflow', 500);
	}

	private static function isdbTypeNumeric(string $type): bool
	{
		return false != preg_match(
			'/^'.implode(
				'|',
				[
					'TINYINT',
					'SMALLINT',
					'MEDIUMINT',
					'INT',
					'BIGINT',
					'DOUBLE',
					'FLOAT',
				]
			).'/',
			$type
		);
	}

	private static function quoteDbValue(mixed $value, ?string $type): string
	{
		if(!isset($type) || !self::isdbTypeNumeric($type))
			return '\''.$value.'\'';
		else
			return $value;
	}
}
