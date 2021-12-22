<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Core\Comparator;
use Reflexive\Query;
use ReflectionProperty;

class Create extends ModelStatement
{
	public function __construct(
		private Model &$model
	)
	{
		parent::__construct($model::class);
		$this->query = Query\Composed::Insert();
	}

	public function execute(\PDO $database)
	{
		$this->initSchema();

		foreach($this->schema->getColumns() as $propertyName => $column) {
			$propertyReflexion = new ReflectionProperty($this->model, $propertyName);
			$propertyReflexion->setAccessible(true);

			$value = $propertyReflexion->getValue($this->model);
			if(is_bool($value))
				$value = (int)$value;

			if(!@$column['autoIncrement'])
				$this->query->set($column['name'], $value);
		}

		$statement = $this->query->prepare($database);
		$statement->execute();

		$this->model->setId($database->lastInsertId());
	}
}
