<?php

declare(strict_types=1);

namespace Reflexive\Model;

use ReflectionProperty;
use ReflectionUnionType;
use ReflectionIntersectionType;

use Reflexive\Core\Comparator;
use Reflexive\Query;

class Update extends Push
{
	public function __construct(Model &$model)
	{
		parent::__construct($model);
		$this->query = new Query\Update();
		// $this->where('id', Comparator::EQUAL, $model->getId());

		$this->initSchema();

		if(isset($this->schema)) {
			$this->query->where($this->schema->getUIdColumnName(), Comparator::EQUAL, $this->model->getId());
		}

		$modifiedPropertiesNames = $this->model->getModifiedPropertiesNames();
		if($this->model->ignoreModifiedProperties || (!$this->model->ignoreModifiedProperties && !empty($modifiedPropertiesNames))) {
			foreach($this->schema->getColumns() as $propertyName => $column) {
				if((!$this->model->ignoreModifiedProperties && !in_array($propertyName, $modifiedPropertiesNames)) || @$column['autoIncrement'])
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

			foreach($this->schema->getReferences() as $reference) {
				var_dump($reference);
			}
		}
	}
}
