<?php

declare(strict_types=1);

namespace Reflexive\Model;

class Search extends Pull
{
	/*
	 * @throws \TypeError
	 */
	public function execute(\PDO $database): ModelCollection
	{
		$this->init();

		if(isset($this->schema)) {
			$this->query->setColumns($this->schema->getColumnNames());

			$this->query->order($this->schema->getTableName().'.'.$this->schema->getUIdColumnName());
		}

		return new ModelCollection(
			$this->modelClassName,
			$this->query->prepare($database),
			$this->getInstanciator(),
			$this->query->getLimit(),
			$this->query->getOffset() ?? 0,
			$database
		);
	}
}
