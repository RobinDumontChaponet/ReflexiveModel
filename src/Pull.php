<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Core\Comparator;
use Reflexive\Query;

abstract class Pull extends ModelStatement
{
	// protected Query\Select $query;

	public function __construct(string $modelClassName)
	{
		parent::__construct($modelClassName);
		$this->query = new Query\Select();

		// $this->init();

		$schema = $this->schema ?? Schema::getSchema($this->modelClassName);
		if(($superType = $schema->getSuperType()) !== null && ($superTypeSchema = Schema::getSchema($superType))) { // is subType of $superType
			$this->query->join(
				Query\Join::inner,
				$superTypeSchema->getTableName(),
				$superTypeSchema->getUIdColumnNameString(),
				Comparator::EQUAL,
				$schema->getTableName(),
				$schema->getUidColumnNameString(),
			);
		}
	}

	public function with(string $propertyName, Comparator $comparator, ?Model $reference = null): static
	{
		$this->init();

		$referencedSchema = Schema::getSchema($reference::class);
		if(!isset($referencedSchema)) {
			throw new \TypeError('Schema "'.$reference::class.'" not set');
		}

		if($referencedSchema->hasReference($propertyName)) {
			if($referencedSchema->getReferenceCardinality($propertyName) == Cardinality::ManyToMany) {
				$this->query->join(
					Query\Join::inner,
					$referencedSchema->getReferenceForeignTableName($propertyName),
					$referencedSchema->getReferenceForeignRightColumnName($propertyName),
					Comparator::EQUAL,
					$this->schema->getTableName(),
					$this->schema->getUidColumnNameString(),
				);
				$this->query->and(
					$referencedSchema->getReferenceForeignTableName($propertyName).'.'.$referencedSchema->getReferenceForeignColumnName($propertyName),
					$comparator,
					$reference->getModelId(),
				);
			}
		} else {
			throw new \TypeError('Reference "'.$propertyName.'" not found in Schema "'.$this->schema->getTableName().'" / referencedSchema "'.$referencedSchema->getTableName().'"');
		}

		return $this;
	}

	public function order(string $propertyName, Query\Direction $direction = Query\Direction::ASC): static
	{
		$this->init();

		if($this->schema->hasColumn($propertyName)) {
			$this->query->order($this->schema->getColumnName($propertyName), $direction);
		} elseif(($superType = $this->schema->getSuperType()) !== null) {
			if(($superTypeSchema = Schema::getSchema($superType)) && $superTypeSchema->hasColumn($propertyName))
				$this->query->order($superTypeSchema->getColumnName($propertyName), $direction);
		} else {
			throw new \TypeError('Property "'.$propertyName.'" not found in Schema "'.$this->schema->getTableName().'". Could not order by.');
		}

		return $this;
	}
}
