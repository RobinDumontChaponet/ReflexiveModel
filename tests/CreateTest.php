<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Core\Database;
use Reflexive\Model\Column;
use Reflexive\Model\Create;
use Reflexive\Model\Model;
use Reflexive\Model\ModelId;
use Reflexive\Model\Property;
use Reflexive\Model\Table;

#[Table('create_test_records')]
final class CreateTestRecord extends Model
{
	use ModelId;

	#[Property]
	#[Column('name')]
	protected string $name = '';

	#[Property]
	#[Column('active')]
	protected bool $active = false;
}

final class CreateTest extends TestCase
{
	private Database $database;

	protected function setUp(): void
	{
		$this->database = new Database('sqlite::memory:');
		$this->database->exec('CREATE TABLE create_test_records (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, active INTEGER NOT NULL)');
	}

	public function testCreateInsertsModelAndAssignsAutoIncrementId(): void
	{
		// Verifies create persists non-id columns and writes the inserted id back.
		$model = new CreateTestRecord();
		$model->setName('Created');
		$model->setActive(true);

		$result = (new Create(CreateTestRecord::class, $model))->execute($this->database);

		$this->assertTrue($result);
		$this->assertSame(['id' => 1], $model->getModelId());
		$this->assertSame('1', $model->getModelIdString());
		$this->assertSame('Created', $this->database->query('SELECT name FROM create_test_records WHERE id = 1')->fetchColumn());
		$this->assertSame(1, (int) $this->database->query('SELECT active FROM create_test_records WHERE id = 1')->fetchColumn());
	}

	public function testCreateStringContainsInsertStatement(): void
	{
		// Verifies create builds an insert query for writable model columns.
		$model = new CreateTestRecord();
		$model->setName('Prepared');

		$sql = (string) new Create(CreateTestRecord::class, $model);

		$this->assertStringContainsString('INSERT INTO `create_test_records`', $sql);
		$this->assertStringContainsString('`name`', $sql);
		$this->assertStringContainsString('`active`', $sql);
		$this->assertStringNotContainsString('`id`', $sql);
	}
}
