<?php

declare(strict_types=1);

namespace Reflexive\Model;

class Search extends Pull
{
	public function execute(\PDO $database)
	{
		$this->initSchema();

		if(isset($this->schema)) {
			// $this->query->setColumns($this->schema->getColumnNames());

			$this->query->order($this->schema->getTableName().'.'.$this->schema->getUIdColumnName());
		}

		return new ModelCollection(
			$this->query->prepare($database),
			self::$instanciators[$this->modelClassName],
			$this->query->getLimit(),
			$this->query->getOffset() ?? 0,
			$database
		);
	}
}
