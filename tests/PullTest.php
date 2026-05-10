<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Model\Column;
use Reflexive\Model\Model;
use Reflexive\Model\ModelId;
use Reflexive\Model\Property;
use Reflexive\Model\Search;
use Reflexive\Model\Table;
use Reflexive\Query\Direction;

#[Table('pull_test_records')]
final class PullTestRecord extends Model
{
	use ModelId;

	#[Property]
	#[Column('name')]
	protected string $name = '';

	#[Property]
	#[Column('active')]
	protected bool $active = false;
}

final class PullTest extends TestCase
{
	public function testGroupUsesSchemaColumnName(): void
	{
		// Verifies grouping by a model property maps to its database column.
		$sql = (string) (new Search(PullTestRecord::class))->group('active');

		$this->assertStringContainsString('GROUP BY `active`', $sql);
	}

	public function testOrderUsesSchemaColumnNameAndDirection(): void
	{
		// Verifies ordering by a model property maps to its database column.
		$sql = (string) (new Search(PullTestRecord::class))->order('name', Direction::DESC);

		$this->assertStringContainsString('ORDER BY `name`  DESC', $sql);
	}

	public function testGroupRejectsUnknownProperty(): void
	{
		// Verifies grouping fails clearly for missing schema properties.
		$this->expectException(\TypeError::class);
		$this->expectExceptionMessage('Could not group by');

		(new Search(PullTestRecord::class))->group('missing');
	}
}
