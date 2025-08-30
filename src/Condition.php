<?php

declare(strict_types=1);

namespace Reflexive\Model;

use DateTimeInterface;
use Reflexive\Query;
use Reflexive\Core\Comparator;

class Condition extends \Reflexive\Core\Condition
{
	public function __construct(
		string $name,
		Comparator $comparator,
		string|int|float|array|bool|Model|ModelCollection|DateTimeInterface|null $value = null
	) {
		parent::__construct($name, $comparator, null);
		$this->value = $value;
	}

	// oh, this is some kind of factory function uh…
	public function and(self $condition): ConditionGroup
	{
		return (new ConditionGroup())
			->where($this)
			->and($condition);
	}
	// oh, this is some kind of factory function uh…
	public function or(self $condition): ConditionGroup
	{
		return (new ConditionGroup())
			->where($this)
			->or($condition);
	}

	public function bake(Schema $schema): array
	{
		$queryCondition = null;
		$joins = [];
		$value = $this->value;

		$targetSchema = null;
		if($schema->hasColumn($this->name)) {
			$targetSchema = $schema;
		} elseif($schema->isSubType()) {
			$superSchema = $schema->getSuperSchema();

			if($superSchema?->hasColumn($this->name)) {
				$targetSchema = $superSchema;
			}
		}

		if($targetSchema) {
			$value = match(gettype($value)) {
				'boolean' => (int)$value,
				'object' => $value instanceof DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value->id,
				default => $value,
			};

			$queryCondition = new Query\Condition(
				$targetSchema->getTableName().'.'.$targetSchema->getColumnName($this->name),
				$this->comparator,
				$value
			);

			return [
				'condition' => $queryCondition,
			];
		}

		$targetSchema = null;
		if($schema->hasReference($this->name)) {
			$targetSchema = $schema;
		} elseif($schema->isSubType()) {
			$superSchema = $schema->getSuperSchema();

			if($superSchema?->hasReference($this->name)) {
				$targetSchema = $superSchema;
			}
		}

		if($targetSchema) {
			$referenceCardinality = $targetSchema->getReferenceCardinality($this->name);

			if($this->comparator == Comparator::IN && (is_array($value) || $value instanceof ModelCollection)) {
				if($value instanceof ModelCollection) {
					$value = $value->asArray();
				}

				$values = array_map(fn($v) => $v->getModelIdString(), $value);

				if(!empty($values)) {
					switch($referenceCardinality) {
						case Cardinality::OneToMany:
							$queryCondition = new Query\Condition(
								$targetSchema->getTableName().'.'.$targetSchema->getReferenceColumnName($this->name),
								$this->comparator,
								$values
							);
						break;
						default:
							throw new \LogicException('Case "'.$referenceCardinality?->name.'" not implemented');
						break;
					}
				} else {
					throw new \LogicException('Mhm. What should I do ?');
				}
			} elseif(is_object($value)) {
				switch($referenceCardinality) {
					case Cardinality::OneToMany:
						$queryCondition = new Query\Condition(
							$targetSchema->getTableName().'.'.$targetSchema->getReferenceColumnName($this->name),
							$this->comparator,
							$value->getModelId(),
						);
					break;
					case Cardinality::ManyToMany:
						$joins[] = [
							Query\Join::inner,
							$targetSchema->getReferenceForeignTableName($this->name),
							$targetSchema->getReferenceForeignColumnName($this->name),
							Comparator::EQUAL,
							$targetSchema->getTableName(),
							$targetSchema->getUidColumnNameString(),
						];
						$queryCondition = new Query\Condition(
							$targetSchema->getReferenceForeignTableName($this->name).'.'.$targetSchema->getReferenceForeignRightColumnName($this->name),
							$this->comparator,
							$value->getModelId()
						);
					break;
					default:
						throw new \LogicException('Case "'.$referenceCardinality?->name.'" not implemented');
					break;
				}
			} elseif(null === $value && $targetSchema->isReferenceNullable($this->name)) {
				$queryCondition = new Query\Condition($targetSchema->getTableName().'.'.$targetSchema->getReferenceColumnName($this->name), $this->comparator, $value);
			} else {
				throw new \TypeError('Can only reference "'.$this->name.'" with object, '.gettype($value).' given.');
			}

			return [
				'condition' => $queryCondition,
				'joins' => $joins,
			];
		}

		throw new \TypeError('Property (or Reference) "'.$this->name.'" not found in Schema "'.$schema->getTableName().'"');

		return [];
	}
}
