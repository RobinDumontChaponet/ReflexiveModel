<?php

declare(strict_types=1);

namespace Reflexive\Model;

use DateTime;
use ReflectionProperty;
use ReflectionUnionType;
use ReflectionIntersectionType;

use Reflexive\Query;
use Reflexive\Core\Comparator;

/**
 * @property Query\Push query
 */
abstract class Push extends ModelStatement
{
	protected array $referencedQueries = [];

	public function __construct(
		string $modelClassName,
		protected Model &$model
	)
	{
		parent::__construct($modelClassName);

		$this->init();

		$modifiedPropertiesNames = $this->model->getModifiedPropertiesNames();
		if($this->model->ignoreModifiedProperties || !empty($modifiedPropertiesNames)) {
			foreach($this->schema->getColumns() as $propertyName => $column) {
				if((!$this->model->ignoreModifiedProperties && !in_array($propertyName, $modifiedPropertiesNames)) || (isset($column['autoIncrement']) && $column['autoIncrement']) || ($this->schema->hasReference($propertyName) && $this->schema->getReferenceCardinality($propertyName) === Cardinality::ManyToMany))
					continue;

				$propertyReflection = new ReflectionProperty($this->model, $propertyName);
				$propertyReflection->setAccessible(true);

				if($propertyReflection->isInitialized($this->model) && null !== $propertyReflection->getValue($this->model)) {
					$value = $propertyReflection->getValue($this->model);

					if($column['type'] == 'json') {
						$this->query->set($column['columnName'], json_encode($value));
					} elseif($type = $propertyReflection->getType()) {
						if($types = $type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType ? $type->getTypes() : [$type]) {
							foreach($types as $type) {
								if($type->isBuiltin()) { // PHP builtin types
									/** @psalm-suppress UndefinedMethod */
									$this->query->set(
										$column['columnName'],
										match($type->getName()) {
											'int' => (int)$value,
											'bool' => (int)$value,
											'double', 'float' => (double)$value,
											'string' => ''.$value,
											default => ''.$value
										}
									);
									break;
								} else {
									if(enum_exists($type->getName())) { // PHP enum
										/** @psalm-suppress UndefinedMethod */
										$this->query->set($column['columnName'], $value->name);
										break;
									} elseif(class_exists($type->getName(), true)) { // object
										/** @psalm-suppress UndefinedMethod */
										$this->query->set(
											$column['columnName'],
											match($type->getName()) {
												'stdClass' => json_encode($value),
												'DateTime' => $value->format('Y-m-d H:i:s'),
												'Reflexive\Model\Model' => $value->getModelId(),
												default => $value->__toString(),
											}
										);
										break;
									}
								}
							}
						} else {
							throw new \LogicException('NO TYPE ? Column "'.$column['columnName'].'" in schema "'.$this->modelClassName.'" from property "'.$propertyName.'" of model "'.$model::class.'"');
							// $this->query->set($column['columnName'], $value);
						}
					}
				} else {
					$defaultValue = $this->schema->getColumnDefaultValue($propertyName);
					if($this->schema->isColumnNullable($propertyName)) {
						/** @psalm-suppress UndefinedMethod */
						$this->query->set($column['columnName'], null);
					} elseif(in_array(strtoupper($defaultValue ?? ''), ['NOW()', 'CURRENT_TIMESTAMP'])) {
						$model->$propertyName = new DateTime();
					} elseif(null !== $defaultValue) {
						/** @psalm-suppress UndefinedMethod */
						$this->query->set($column['columnName'], $defaultValue);
					} else {
						throw new \TypeError('Column "'.$column['columnName'].'" in schema "'.$this->modelClassName.'" cannot take null value from property "'.$propertyName.'" of model "'.$model::class.'"');
					}
				}
			}

			foreach($this->schema->getReferences() as $propertyName => $reference) {
				$propertyReflection = new ReflectionProperty($this->model, $propertyName);
				$propertyReflection->setAccessible(true);

				if((!$this->model->ignoreModifiedProperties && !in_array($propertyName, $this->model->getModifiedPropertiesNames())))
					continue;

				switch($reference['cardinality']) {
					case Cardinality::OneToOne:
						$value = $propertyReflection->isInitialized($this->model) ? $propertyReflection->getValue($this->model) : null;
						if(isset($reference['columnName']) && !is_null($value)) {
							/** @psalm-suppress UndefinedMethod */
							$this->query->set($reference['columnName'], $value);
						}
					break;

					case Cardinality::OneToMany:
						$value = $propertyReflection->isInitialized($this->model) ? $propertyReflection->getValue($this->model) : null;

						if(null === $value) {
							if($this->schema->isReferenceNullable($propertyName)) {
								/** @psalm-suppress UndefinedMethod */
								$this->query->set($reference['columnName'], null);
							} else {
								throw new \TypeError('Reference column "'.$reference['columnName'].'" in schema "'.$this->modelClassName.'" cannot take null value from property (reference) "'.$propertyName.'" of model "'.$model::class.'"');
							}
						} else {
							if(isset($reference['columnName']) && !is_null($value)) {
								/** @psalm-suppress UndefinedMethod */
								$this->query->set($reference['columnName'], $value->getModelIdString());
							}
						}
					break;
				}
			}
		}
	}

	protected function constructOuterReferences(): void
	{
		if($this->model->updateReferences) {
			foreach($this->schema->getReferences() as $propertyName => $reference) {
				$propertyReflection = new ReflectionProperty($this->model, $propertyName);
				$propertyReflection->setAccessible(true);

				if($reference['cardinality'] === Cardinality::ManyToMany) {
					$value = $propertyReflection->isInitialized($this->model) ? $propertyReflection->getValue($this->model) : null;

					if($value instanceof ModelCollection) { // TODO : this is temporary
						foreach($value->getAddedKeys() as $addedKey) {
							$referencedQuery = new Query\Insert();
							$referencedQuery->set($reference['foreignColumnName'], $this->model->getModelIdString())
								->set($reference['foreignRightColumnName'], $addedKey)
								->from($reference['foreignTableName']);

							$this->referencedQueries[] = $referencedQuery;
						}
						foreach($value->getModifiedKeys() as $modifiedKey) {
							$referencedQuery = $reference['type']::update($value[$modifiedKey]);
							$this->referencedQueries[] = $referencedQuery;
						}
						foreach($value->getRemovedKeys() as $removedKey) {
							$referencedQuery = new Query\Delete();
							$referencedQuery->where(new Query\Condition(
									$reference['foreignColumnName'],
									Comparator::EQUAL,
									$this->model->getModelIdString()
								))
								->and(new Query\Condition(
									$reference['foreignRightColumnName'],
									Comparator::EQUAL,
									$removedKey
								))
								->from($reference['foreignTableName']);

							$this->referencedQueries[] = $referencedQuery;
						}
					}
				}
			}
		}
	}

	public function execute(\PDO $database): bool
	{
		$statement = $this->query->prepare($database);
		return $statement->execute();
	}

	public function __toString(): string
	{
		// TODO : add superTypesQueries. ([superTypeName, superTypeSchema, superTypeQuery] from constructor ?)

		return parent::__toString() .'; '. implode('; ', array_map(fn($query) => $query.'', $this->referencedQueries));
	}
}
