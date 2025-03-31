<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Core\Comparator;
use Reflexive\Query;

abstract class PullOne extends ModelStatement
{
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
}
