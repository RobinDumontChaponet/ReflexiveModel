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
use Reflexive\Model\Read;
use Reflexive\Model\Table;

#[Table('model_statement_test_records')]
final class ModelStatementTestRecord extends Model
{
	use ModelId;

	#[Property]
	#[Column('name')]
	protected string $name = '';
}

final class ModelStatementTest extends TestCase
{
	public function testWhereAndAndAddQueryConditions(): void
	{
		// Verifies model statements chain model-level conditions into SQL.
		$sql = (string) (new Read(ModelStatementTestRecord::class))
			->where(new Condition('id', Comparator::GREATER, 1))
			->and(new Condition('name', Comparator::LIKE, 'A%'));

		$this->assertStringContainsString('WHERE (', $sql);
		$this->assertStringContainsString('`model_statement_test_records`.`id` > :model_statement_test_recordsid_0', $sql);
		$this->assertStringContainsString('AND `model_statement_test_records`.`name` LIKE :model_statement_test_recordsname_1', $sql);
	}

	public function testLimitAndOffsetAreAppliedToQuery(): void
	{
		// Verifies limit and offset pass through to the underlying query.
		$sql = (string) (new Read(ModelStatementTestRecord::class))->limit(10)->offset(5);

		$this->assertStringContainsString('LIMIT 10 OFFSET 5', $sql);
	}

	public function testGetQueryReturnsInitializedQuery(): void
	{
		// Verifies getQuery exposes the composed query after initialization.
		$query = (new Read(ModelStatementTestRecord::class))->getQuery();

		$this->assertSame('SELECT `model_statement_test_records`.`id`, `model_statement_test_records`.`name` FROM `model_statement_test_records`; '.PHP_EOL.'/* {'.PHP_EOL.'} */ ', (string) $query);
	}
}
