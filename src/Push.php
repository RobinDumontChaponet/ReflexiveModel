<?php

declare(strict_types=1);

namespace Reflexive\Model;

use ReflectionProperty;
use Reflexive\Core\Comparator;

abstract class Push extends ModelStatement
{
	public function __construct(
		private Model &$model
	)
	{
		parent::__construct($model::class);
	}

	public function execute(\PDO $database)
	{
		$this->initSchema();

		if(isset($this->schema)) {
			$this->query->where($this->schema->getUIdColumnName(), Comparator::EQUAL, $this->model->getId());
		}

		$modifiedPropertiesNames = $this->model->getModifiedPropertiesNames();

		if(!$this->model->ignoreModifiedProperties && !empty($modifiedPropertiesNames)) {
			foreach($this->schema->getColumns() as $propertyName => $column) {
				if((!$this->model->ignoreModifiedProperties && !in_array($propertyName, $modifiedPropertiesNames)) || @$column['autoIncrement'])
					continue;

				$propertyReflexion = new ReflectionProperty($this->model, $propertyName);
				$propertyReflexion->setAccessible(true);

				$value = $propertyReflexion->getValue($this->model);
				if(is_bool($value))
					$value = (int)$value;

				$this->query->set($column['columnName'], $value);
			}

			$statement = $this->query->prepare($database);
			$statement->execute();

			$this->model->setId($database->lastInsertId());

			$this->model->resetModifiedPropertiesNames();
		}
	}
}
