<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Core\Comparator;
use Reflexive\Query\Condition;
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
			$superColumnName = $superTypeSchema->getUIdColumnName();
			if(is_array($superColumnName)) {
				$superColumnName = array_first($superColumnName);
			}

			$columnName = $schema->getUIdColumnName();
			if(is_array($columnName)) {
				$columnName = array_first($columnName);
			}

			$this->query->join(
				Query\Join::inner,
				$superTypeSchema->getTableName(),
				$superColumnName,
				Comparator::EQUAL,
				$schema->getTableName(),
				$columnName,
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

		$targetSchema = null;
		if($referencedSchema->hasReference($propertyName)) {
			$targetSchema = $referencedSchema;
		} elseif($referencedSchema->isSubType()) {
			$superReferencedSchema = Schema::getSchema($referencedSchema->getSuperType());

			if($superReferencedSchema->hasReference($propertyName)) {
				$targetSchema = $superReferencedSchema;
			}
		}

		if($targetSchema) {
			if($targetSchema->getReferenceCardinality($propertyName) == Cardinality::ManyToMany) {
				$columnName = $this->schema->getUIdColumnName();
				if(is_array($columnName)) {
					$columnName = array_first($columnName);
				}

				$this->query->join(
					Query\Join::inner,
					$targetSchema->getReferenceForeignTableName($propertyName),
					$targetSchema->getReferenceForeignRightColumnName($propertyName),
					Comparator::EQUAL,
					$this->schema->getTableName(),
					$columnName,
				);
				$this->query->and(new Condition(
					$targetSchema->getReferenceForeignTableName($propertyName).'.'.$targetSchema->getReferenceForeignColumnName($propertyName),
					$comparator,
					$reference->getModelId(),
				));
			}
		} else {
			throw new \TypeError('Reference "'.$propertyName.'" not found in Schema "'.$this->schema->getTableName().'" / referencedSchema "'.$referencedSchema->getTableName().'"');
		}

		return $this;
	}
}
