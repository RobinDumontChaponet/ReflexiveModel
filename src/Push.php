<?php

declare(strict_types=1);

namespace Reflexive\Model;

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
		protected Model &$model
	)
	{
		parent::__construct($model::class);

		$this->init();

		$modifiedPropertiesNames = $this->model->getModifiedPropertiesNames();
		if($this->model->ignoreModifiedProperties || (!$this->model->ignoreModifiedProperties && !empty($modifiedPropertiesNames))) {
			foreach($this->schema->getColumns() as $propertyName => $column) {
				if((!$this->model->ignoreModifiedProperties && !in_array($propertyName, $modifiedPropertiesNames)) || (isset($column['autoIncrement']) && $column['autoIncrement']) || ($this->schema->hasReference($propertyName) && $this->schema->getReferenceCardinality($propertyName) === Cardinality::ManyToMany))
					continue;

				$propertyReflection = new ReflectionProperty($this->model, $propertyName);
				$propertyReflection->setAccessible(true);

				if($propertyReflection->isInitialized($this->model) && null !== $propertyReflection->getValue($this->model)) {
					$value = $propertyReflection->getValue($this->model);

					if($type = $propertyReflection->getType()) {
						if($types = $type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType ? $type->getTypes() : [$type]) {
							foreach($types as $type) {
								if($type->isBuiltin()) { // PHP builtin types
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
										$this->query->set($column['columnName'], $value->value);
										break;
									} elseif(class_exists($type->getName(), true)) { // object
										$this->query->set(
											$column['columnName'],
											match($type->getName()) {
												'DateTime' => $value->format('Y-m-d H:i:s'),
												'Reflexive\Model\Model' => $value->getId(),
											}
										);
										break;
									}
								}
							}
						} else {
							var_dump('NO TYPE ?');
							// $this->query->set($column['columnName'], $value);
						}
					}
				} else {
					if($this->schema->isColumnNullable($propertyName))
						$this->query->set($column['columnName'], null);
					elseif(null !== ($defaultValue = $this->schema->getColumnDefaultValue($propertyName))) {
						if(!in_array(strtoupper($defaultValue), ['NOW()', 'CURRENT_TIMESTAMP']))
							$this->query->set($column['columnName'], $defaultValue);
					} else
						throw new \TypeError('Column "'.$column['columnName'].'" in schema "'.$this->modelClassName.'" cannot take null value from property "'.$propertyName.'" of model "'.$model::class.'"');
				}
			}

			foreach($this->schema->getReferences() as $propertyName => $reference) {
				$propertyReflection = new ReflectionProperty($this->model, $propertyName);
				$propertyReflection->setAccessible(true);


				if($reference['cardinality'] === Cardinality::OneToMany) {
					if((!$this->model->ignoreModifiedProperties && !in_array($propertyName, $this->model->getModifiedPropertiesNames())))
						continue;

					$value = $propertyReflection->isInitialized($this->model) ? $propertyReflection->getValue($this->model) : null;
					if(isset($reference['columnName']) && !is_null($value)) {
						$this->query->set($reference['columnName'], $value->getId());
					}
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
							$referencedQuery->set($reference['foreignColumnName'], $this->model->getId())
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
							$referencedQuery->where($reference['foreignColumnName'], Comparator::EQUAL, $this->model->getId())
								->and($reference['foreignRightColumnName'], Comparator::EQUAL, $removedKey)
								->from($reference['foreignTableName']);

							$this->referencedQueries[] = $referencedQuery;
						}
					}
				}
			}
		}
	}

	public function execute(\PDO $database)
	{
		$statement = $this->query->prepare($database);
		$execute = $statement->execute();

		return $execute;
	}
}
