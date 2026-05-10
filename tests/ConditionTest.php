<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Core\Comparator;
use Reflexive\Model\Column;
use Reflexive\Model\Condition;
use Reflexive\Model\Model;
use Reflexive\Model\ModelId;
use Reflexive\Model\Property;
use Reflexive\Model\Schema;
use Reflexive\Model\Table;
use Reflexive\Query\Condition as QueryCondition;

enum ConditionTestStatus
{
	case Draft;
	case Published;
}

#[Table('condition_test_articles')]
final class ConditionTestArticle extends Model
{
	use ModelId;

	#[Property]
	#[Column('published')]
	protected bool $published = false;

	#[Property]
	#[Column('status')]
	protected ConditionTestStatus $status = ConditionTestStatus::Draft;
}

final class ConditionTest extends TestCase
{
	public function testBakeMapsModelPropertyToQualifiedColumnCondition(): void
	{
		// Verifies model-level conditions bake into query conditions with converted values.
		$schema = Schema::getSchema(ConditionTestArticle::class);
		$baked = (new Condition('published', Comparator::EQUAL, true))->bake($schema);

		$this->assertArrayHasKey('condition', $baked);
		$this->assertInstanceOf(QueryCondition::class, $baked['condition']);
		$this->assertSame('condition_test_articles.published', $baked['condition']->name);
		$this->assertSame(Comparator::EQUAL, $baked['condition']->comparator);
		$this->assertSame(1, $baked['condition']->value);
	}

	public function testBakeMapsEnumColumnValueToCaseName(): void
	{
		// Verifies enum values on model columns bake to their stable case names.
		$schema = Schema::getSchema(ConditionTestArticle::class);
		$baked = (new Condition('status', Comparator::EQUAL, ConditionTestStatus::Published))->bake($schema);

		$this->assertSame('Published', $baked['condition']->value);
	}

	public function testBakeRejectsUnknownModelProperty(): void
	{
		// Verifies conditions fail clearly when the schema has no matching property.
		$schema = Schema::getSchema(ConditionTestArticle::class);

		$this->expectException(\TypeError::class);
		$this->expectExceptionMessage('Property (or Reference) "missing" not found');

		(new Condition('missing', Comparator::EQUAL, 'value'))->bake($schema);
	}
}
