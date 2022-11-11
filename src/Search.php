<?php

declare(strict_types=1);

namespace Reflexive\Model;

class Search extends Pull
{
	public function __construct(string $modelClassName)
	{
		parent::__construct($modelClassName);

		$this->init();

		$this->query->order($this->schema->getTableName().'.'.$this->schema->getUIdColumnNameString());
	}

	/*
	 * @throws \TypeError
	 */
	public function execute(\PDO $database): ModelCollection
	{
		$this->init();

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
