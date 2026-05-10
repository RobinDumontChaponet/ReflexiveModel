<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Core\Database;
use Reflexive\Model\Column;
use Reflexive\Model\Count;
use Reflexive\Model\Model;
use Reflexive\Model\ModelId;
use Reflexive\Model\Property;
use Reflexive\Model\Table;

#[Table('count_test_records')]
final class CountTestRecord extends Model
{
	use ModelId;

	#[Property]
	#[Column('active')]
	protected bool $active = false;
}

final class CountTest extends TestCase
{
	private Database $database;

	protected function setUp(): void
	{
		$this->database = new Database('sqlite::memory:');
		$this->database->exec('CREATE TABLE count_test_records (id INTEGER PRIMARY KEY AUTOINCREMENT, active INTEGER NOT NULL)');
		$this->database->exec('INSERT INTO count_test_records (active) VALUES (1), (0), (1)');
	}

	public function testCountReturnsTotalRows(): void
	{
		// Verifies count executes and returns the aggregate count.
		$this->assertSame(3, CountTestRecord::count()->execute($this->database));
	}

	public function testCountCanGroupByModelProperty(): void
	{
		// Verifies grouped counts are keyed by database group values.
		$this->assertSame([0 => 1, 1 => 2], CountTestRecord::count()->group('active')->execute($this->database));
	}

	public function testCountStringSelectsCountExpression(): void
	{
		// Verifies count builds a COUNT(*) select.
		$sql = (string) new Count(CountTestRecord::class);

		$this->assertStringContainsString('SELECT COUNT(*) c FROM `count_test_records`', $sql);
	}
}
