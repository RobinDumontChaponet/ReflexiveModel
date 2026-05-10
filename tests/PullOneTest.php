<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Model\Column;
use Reflexive\Model\Model;
use Reflexive\Model\ModelId;
use Reflexive\Model\Property;
use Reflexive\Model\Read;
use Reflexive\Model\Table;

#[Table('pull_one_test_records')]
final class PullOneTestRecord extends Model
{
	use ModelId;

	#[Property]
	#[Column('name')]
	protected string $name = '';
}

final class PullOneTest extends TestCase
{
	public function testConstructorCreatesSelectStatementForModelSchema(): void
	{
		// Verifies PullOne descendants initialize a schema-backed select query.
		$sql = (string) new Read(PullOneTestRecord::class);

		$this->assertStringContainsString('SELECT `pull_one_test_records`.`id`, `pull_one_test_records`.`name`', $sql);
		$this->assertStringContainsString('FROM `pull_one_test_records`', $sql);
	}
}
