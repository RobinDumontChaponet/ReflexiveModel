<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Core\Operator;
use Reflexive\Query;

class ConditionGroup extends \Reflexive\Core\ConditionGroup
{
	protected array $parameters = [];

	public function __construct(
		?Condition $firstCondition = null
	) {
		parent::__construct($firstCondition);
	}

	public function bake(Schema $schema): array
	{
		$conditionGroup = new Query\ConditionGroup();
		$joins = [];

		foreach($this->conditions as $conditionArray) {
			var_dump($conditionArray['condition']->name);

			$baked = $conditionArray['condition']->bake($schema);

			switch($conditionArray['operator']) {
				case Operator::OR:
					$conditionGroup->or($baked['conditions'] ?? $baked['condition']);
				break;
				default:
					$conditionGroup->and($baked['conditions'] ?? $baked['condition']);
				break;
			}
			$joins += $baked['joins'] ?? [];
		}

		return [
			'conditions' => $conditionGroup,
			'joins' => $joins,
		];
	}
}
