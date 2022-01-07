<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Core\Comparator;
use Reflexive\Query;
use ReflectionProperty;

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

		$modifiedPropertiesNames = $this->model->getModifiedPropertiesNames();
		foreach($this->schema->getColumns() as $propertyName => $column) {
			if((!$this->model->ignoreModifiedProperties && !in_array($propertyName, $modifiedPropertiesNames)) || @$column['autoIncrement'])
				continue;

			$propertyReflexion = new ReflectionProperty($this->model, $propertyName);
			$propertyReflexion->setAccessible(true);

			$value = $propertyReflexion->getValue($this->model);
			if(is_bool($value))
				$value = (int)$value;

			$this->query->set($column['name'], $value);
		}

		$statement = $this->query->prepare($database);
		$statement->execute();

		$this->model->setId($database->lastInsertId());

		$this->model->resetModifiedPropertiesNames();
	}
}
