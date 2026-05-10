<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Core\Database;
use Reflexive\Model\Column;
use Reflexive\Model\Delete;
use Reflexive\Model\Model;
use Reflexive\Model\ModelId;
use Reflexive\Model\Property;
use Reflexive\Model\Table;

#[Table('delete_test_records')]
final class DeleteTestRecord extends Model
{
	use ModelId;

	#[Property]
	#[Column('name')]
	protected string $name = '';
}

final class DeleteTest extends TestCase
{
	private Database $database;

	protected function setUp(): void
	{
		$this->database = new Database('sqlite::memory:');
		$this->database->exec('CREATE TABLE delete_test_records (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
		$this->database->exec("INSERT INTO delete_test_records (name) VALUES ('Delete me')");
	}

	public function testDeleteRemovesRowByModelId(): void
	{
		// Verifies delete executes using the model's identity.
		$model = new DeleteTestRecord();
		$model->id = 1;

		$this->assertTrue((new Delete(DeleteTestRecord::class, $model))->execute($this->database));
		$this->assertSame(0, (int) $this->database->query('SELECT COUNT(*) FROM delete_test_records')->fetchColumn());
	}

	public function testDeleteStringContainsWhereOnModelId(): void
	{
		// Verifies delete builds a where clause from the model identity.
		$model = new DeleteTestRecord();
		$model->id = 1;

		$sql = (string) new Delete(DeleteTestRecord::class, $model);

		$this->assertStringContainsString('DELETE  FROM `delete_test_records`', $sql);
		$this->assertStringContainsString('WHERE `delete_test_records`.`id`', $sql);
	}
}
