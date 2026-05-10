<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Core\Database;
use Reflexive\Model\Column;
use Reflexive\Model\Model;
use Reflexive\Model\ModelId;
use Reflexive\Model\Property;
use Reflexive\Model\Table;
use Reflexive\Model\Update;

#[Table('update_test_records')]
final class UpdateTestRecord extends Model
{
	use ModelId;

	#[Property]
	#[Column('name')]
	protected string $name = '';

	#[Property]
	#[Column('active')]
	protected bool $active = false;
}

final class UpdateTest extends TestCase
{
	private Database $database;

	protected function setUp(): void
	{
		$this->database = new Database('sqlite::memory:');
		$this->database->exec('CREATE TABLE update_test_records (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, active INTEGER NOT NULL)');
		$this->database->exec("INSERT INTO update_test_records (name, active) VALUES ('Before', 0)");
	}

	public function testUpdatePersistsModifiedColumnsAndResetsDirtyTracking(): void
	{
		// Verifies update writes changed columns and clears modified property names.
		$model = new UpdateTestRecord();
		$model->id = 1;
		$model->resetModifiedPropertiesNames();
		$model->setName('After');
		$model->setActive(true);

		$result = (new Update(UpdateTestRecord::class, $model))->execute($this->database);

		$this->assertTrue($result);
		$this->assertSame('After', $this->database->query('SELECT name FROM update_test_records WHERE id = 1')->fetchColumn());
		$this->assertSame(1, (int) $this->database->query('SELECT active FROM update_test_records WHERE id = 1')->fetchColumn());
		$this->assertSame([], $model->getModifiedPropertiesNames());
	}

	public function testUpdateReturnsFalseWhenNoColumnsWereModified(): void
	{
		// Verifies update short-circuits when no writable fields changed.
		$model = new UpdateTestRecord();
		$model->id = 1;
		$model->resetModifiedPropertiesNames();

		$this->assertFalse((new Update(UpdateTestRecord::class, $model))->execute($this->database));
	}

	public function testUpdateStringContainsWhereOnModelId(): void
	{
		// Verifies update builds a where clause from the model identity.
		$model = new UpdateTestRecord();
		$model->id = 1;
		$model->resetModifiedPropertiesNames();
		$model->setName('After');

		$sql = (string) new Update(UpdateTestRecord::class, $model);

		$this->assertStringContainsString('UPDATE `update_test_records` SET `name`=:name_0', $sql);
		$this->assertStringContainsString('WHERE `update_test_records`.`id` = :update_test_recordsid_0', $sql);
	}
}
