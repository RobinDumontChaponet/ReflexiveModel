<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Core\Comparator;
use Reflexive\Query;
use ReflectionProperty;

class Update extends ModelStatement
{
	public function __construct(
		private Model $model
	)
	{
		parent::__construct($model::class);
		$this->query = Query\Composed::Update();
		$this->where('id', Comparator::EQUAL, $model->getId());
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

			$this->query->set($column['name'], $value);
		}

		// $this->query->bake();
		$statement = $this->query->prepare($database);
		// echo $this->query;

		$statement->execute();

// 		if($rs = $statement->fetch(\PDO::FETCH_OBJ)) {
// 			return self::$generators[$this->modelClassName]($rs)[1];
// 		}
//
// 		return null;
	}
}
