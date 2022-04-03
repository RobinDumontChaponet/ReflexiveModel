<?php

declare(strict_types=1);

namespace Reflexive\Model;

use ReflectionProperty;
use ReflectionUnionType;
use ReflectionIntersectionType;

abstract class Push extends ModelStatement
{
	public function __construct(
		protected Model &$model
	)
	{
		parent::__construct($model::class);

		$this->initSchema();

		$modifiedPropertiesNames = $this->model->getModifiedPropertiesNames();
		if($this->model->ignoreModifiedProperties || (!$this->model->ignoreModifiedProperties && !empty($modifiedPropertiesNames))) {
			foreach($this->schema->getColumns() as $propertyName => $column) {
				if((!$this->model->ignoreModifiedProperties && !in_array($propertyName, $modifiedPropertiesNames)) || (isset($column['autoIncrement']) && $column['autoIncrement']))
					continue;

				$propertyReflection = new ReflectionProperty($this->model, $propertyName);
				$propertyReflection->setAccessible(true);

				if($propertyReflection->isInitialized($this->model)) {
					$value = $propertyReflection->getValue($this->model);
					// if(is_bool($value))
					// 	$value = (int)$value;

					if(null === $value && $this->schema->isColumnNullable($column['columnName'])) {
						$this->query->set($column['columnName'], null);
					} elseif($type = $propertyReflection->getType()) {
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
					if($this->schema->isColumnNullable($column['columnName']))
						$this->query->set($column['columnName'], null);
					else
						throw new \TypeError('Column "'.$column['columnName'].'" in schema "'.$this->modelClassName.'" cannot take null value from model "'.$propertyName.'"');
				}
			}

			// echo '<pre>';
			// echo $this->schema;
			// echo '</pre>';

			foreach($this->schema->getReferences() as $propertyName => $reference) {
				$propertyReflection = new ReflectionProperty($this->model, $propertyName);
				$propertyReflection->setAccessible(true);

				if(!$this->model->ignoreModifiedProperties && !in_array($propertyName, $modifiedPropertiesNames))
					continue;

				switch($reference['cardinality']) {
					case Cardinality::OneToMany:
						$value = $propertyReflection->getValue($this->model);
						if(isset($reference['columnName']) && !is_null($value)) {
							$this->query->set($reference['columnName'], $value->getId());
						}
					break;
					default:
						// var_dump($reference);
					break;
				}
			}
		}
	}

	public function execute(\PDO $database)
	{
		$statement = $this->query->prepare($database);
		return $statement->execute();
	}
}
