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
	}

	public function with(string $propertyName, Comparator $comparator, Model $reference = null): static
	{
		$this->initSchema();

		$referencedSchema = Schema::getCache()[$reference::class];
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
					$this->schema->getUidColumnName(),
				);
				$this->query->and(
					$referencedSchema->getReferenceForeignTableName($propertyName).'.'.$referencedSchema->getReferenceForeignColumnName($propertyName),
					$comparator,
					$reference->getId(),
				);
			}
		} else {
			throw new \TypeError('Reference "'.$propertyName.'" not found in Schema "'.$this->schema->getTableName().'"');
		}

		return $this;
	}
}
