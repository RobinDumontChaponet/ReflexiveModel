<?php

declare(strict_types=1);

namespace Reflexive\Model;

class Read extends Pull
{
	public function execute(\PDO $database)
	{
		$this->init();

		if(isset($this->schema)) {
			// $this->query->setColumns($this->schema->getColumnNames());
		}

		$conditions = $this->query->getConditions();
		if(count($conditions) == 1 && isset($conditions[$this->schema->getUIdColumnName()]))
			if(($object = static::_getModel($this->modelClassName, $conditions[$this->schema->getUIdColumnName()]['value'])) !== null)
				return $object;

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
