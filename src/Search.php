<?php

declare(strict_types=1);

namespace Reflexive\Model;

class Search extends Pull
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

	/*
	 * @throws \TypeError
	 */
	public function execute(\PDO $database): ModelCollection|array
	{
		$this->init();

		// if(!$this->query->isOrdered())
			$this->query->order($this->schema->getTableName().'.'.$this->schema->getUIdColumnNameString());

		if($this->groupedBy) {
			$array = [];
			// while (($rs = $query->fetch(\PDO::FETCH_OBJ)) !== false) {
			// 	$array[$rs->g] = $rs->c;
			// }
			throw new \LogicException('Not yet implemented');
			return $array;
		} else {
			return new ModelCollection(
				$this->modelClassName,
				$this->query,
				Hydrator::getHydrator($this->modelClassName),
				$this->query->getLimit(),
				$this->query->getOffset() ?? 0,
				$database
			);
		}
	}
}
