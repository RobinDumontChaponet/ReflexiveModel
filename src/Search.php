<?php

declare(strict_types=1);

namespace Reflexive\Model;

class Search extends Pull
{
	public function __construct(string $modelClassName)
	{
		parent::__construct($modelClassName);

		$this->init();

		if(isset($this->schema)) {
			if(($superType = $this->schema->getSuperType()) !== null && ($superTypeSchema = Schema::getSchema($superType))) { // is subType of $superType
				$this->query->setColumns(array_merge($this->schema->getColumnNames(), $superTypeSchema->getColumnNames()));
			} else {
				$this->query->setColumns($this->schema->getColumnNames());
			}

			$this->query->order($this->schema->getTableName().'.'.$this->schema->getUIdColumnNameString());
		}
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
