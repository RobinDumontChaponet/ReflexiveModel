<?php

declare(strict_types=1);

namespace Reflexive\Model;

class Read extends PullOne
{
	public function __construct(string $modelClassName)
	{
		parent::__construct($modelClassName);

		$this->init();

		$schema = $this->schema ?? Schema::getSchema($this->modelClassName);
		if($schema->isSuperType()) {
			$this->query->setColumns(array_merge($schema->getUIdColumnName(), ['reflexive_subType']));
		} elseif(($superType = $schema->getSuperType()) !== null && ($superTypeSchema = Schema::getSchema($superType))) { // is subType of $superType
			$this->query->setColumns(array_merge($schema->getColumnNames(), $superTypeSchema->getColumnNames()));
		} else {
			$this->query->setColumns($schema->getColumnNames());
		}
	}

	public function execute(\PDO $database): ?Model
	{
		$this->init();

		// if(isset($this->schema)) {
			// $this->query->setColumns($this->schema->getColumnNames());
		// }

		if($this->query->conditionCount() == 1) {
			$conditions = $this->query->getConditionGroup()->getConditions();
			$id = $conditions[$this->schema->getTableName().'.'.$this->schema->getUIdColumnNameString().'_0']?->value ?? null;

			if(null !== $id && ($object = static::_getModel($this->modelClassName, $id)) !== null)
				return $object;
		}

		$statement = $this->query->prepare($database);
		if($statement->execute()) {
			if($rs = $statement->fetch(\PDO::FETCH_OBJ)) {
				return $this->getInstanciator()(
					$rs,
					$database
				)[1];
			}
		}

		return null;
	}
}
