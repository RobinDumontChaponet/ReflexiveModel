<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Core\Comparator;
use Reflexive\Model\Column;
use Reflexive\Model\Condition;
use Reflexive\Model\ConditionGroup;
use Reflexive\Model\Model;
use Reflexive\Model\ModelId;
use Reflexive\Model\Property;
use Reflexive\Model\Schema;
use Reflexive\Model\Table;
use Reflexive\Query\ConditionGroup as QueryConditionGroup;

#[Table('condition_group_test_records')]
final class ConditionGroupTestRecord extends Model
{
	use ModelId;

	#[Property]
	#[Column('name')]
	protected string $name = '';

	#[Property]
	#[Column('active')]
	protected bool $active = false;
}

final class ConditionGroupTest extends TestCase
{
	public function testBakeCombinesBakedConditions(): void
	{
		// Verifies condition groups bake nested model conditions into query groups.
		$schema = Schema::getSchema(ConditionGroupTestRecord::class);
		$group = (new ConditionGroup())
			->where(new Condition('name', Comparator::EQUAL, 'A'))
			->and(new Condition('active', Comparator::EQUAL, true));

		$baked = $group->bake($schema);

		$this->assertArrayHasKey('conditions', $baked);
		$this->assertInstanceOf(QueryConditionGroup::class, $baked['conditions']);
		$this->assertSame([], $baked['joins']);
		$this->assertSame(2, $baked['conditions']->count());
	}

	public function testConditionAndFactoryCreatesGroup(): void
	{
		// Verifies condition factories create groups with both conditions.
		$group = Condition::EQUAL('name', 'A')->and(Condition::EQUAL('active', true));

		$this->assertInstanceOf(ConditionGroup::class, $group);
		$this->assertSame(2, $group->count());
	}
}
