<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Query;

abstract class Pull extends PullOne
{
	public function group(string $propertyName): static
	{
		$this->init();

		if($this->schema->hasColumn($propertyName)) {
			$this->query->group($this->schema->getColumnName($propertyName));
			$this->groupedBy = $this->schema->getColumnName($propertyName);
		} elseif(($superType = $this->schema->getSuperType()) !== null) {
			if(($superTypeSchema = Schema::getSchema($superType)) && $superTypeSchema->hasColumn($propertyName)) {
				$this->query->group($superTypeSchema->getColumnName($propertyName));
				$this->groupedBy = $superTypeSchema->getColumnName($propertyName);
			}
		} else {
			throw new \TypeError('Property "'.$propertyName.'" not found in Schema "'.$this->schema->getTableName().'". Could not group by.');
		}

		return $this;
	}

	public function order(string $propertyName, Query\Direction $direction = Query\Direction::ASC): static
	{
		$this->init();

		if($this->schema->hasColumn($propertyName)) {
			$columnName = $this->schema->getColumnName($propertyName);
			$this->query->order($columnName, $direction, $this->schema->isColumnNullable($columnName));
		} elseif(($superType = $this->schema->getSuperType()) !== null) {
			if(($superTypeSchema = Schema::getSchema($superType)) && $superTypeSchema->hasColumn($propertyName)) {
				$columnName = $superTypeSchema->getColumnName($propertyName);
				$this->query->order($columnName, $direction, $superTypeSchema->isColumnNullable($columnName));
			}
		} else {
			throw new \TypeError('Property "'.$propertyName.'" not found in Schema "'.$this->schema->getTableName().'". Could not order by.');
		}

		return $this;
	}
}
