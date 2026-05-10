<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Core\Comparator;
use Reflexive\Core\Database;
use Reflexive\Model\Column;
use Reflexive\Model\Model;
use Reflexive\Model\ModelId;
use Reflexive\Model\Property;
use Reflexive\Model\Read;
use Reflexive\Model\Table;

#[Table('read_test_records')]
final class ReadTestRecord extends Model
{
	use ModelId;

	#[Property]
	#[Column('name')]
	protected string $name = '';

	#[Property]
	#[Column('active')]
	protected bool $active = false;
}

final class ReadTest extends TestCase
{
	private Database $database;

	protected function setUp(): void
	{
		$this->database = new Database('sqlite::memory:');
		$this->database->exec('CREATE TABLE read_test_records (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, active INTEGER NOT NULL)');
		$this->database->exec("INSERT INTO read_test_records (name, active) VALUES ('First', 1), ('Second', 0)");
	}

	public function testReadFetchesSingleHydratedModel(): void
	{
		// Verifies read executes and hydrates the first matching row.
		$model = ReadTestRecord::read('id', Comparator::EQUAL, 1)->execute($this->database);

		$this->assertInstanceOf(ReadTestRecord::class, $model);
		$this->assertSame('First', $model->getName());
		$this->assertTrue($model->isActive());
		$this->assertSame('1', $model->getModelIdString());
	}

	public function testReadReturnsNullWhenNoRowsMatch(): void
	{
		// Verifies read returns null for an empty result set.
		$this->assertNull(ReadTestRecord::read('id', Comparator::EQUAL, 99)->execute($this->database));
	}

	public function testReadStringSelectsAllSchemaColumns(): void
	{
		// Verifies read builds a select query with schema-qualified columns.
		$sql = (string) new Read(ReadTestRecord::class);

		$this->assertStringContainsString('SELECT `read_test_records`.`id`, `read_test_records`.`name`, `read_test_records`.`active`', $sql);
		$this->assertStringContainsString('FROM `read_test_records`', $sql);
	}
}
