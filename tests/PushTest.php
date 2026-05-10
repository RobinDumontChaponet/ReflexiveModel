<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Model\Column;
use Reflexive\Model\Create;
use Reflexive\Model\Model;
use Reflexive\Model\ModelId;
use Reflexive\Model\Property;
use Reflexive\Model\Table;

#[Table('push_test_records')]
final class PushTestRecord extends Model
{
	use ModelId;

	#[Property]
	#[Column('name')]
	protected string $name = '';

	#[Property]
	#[Column('active')]
	protected bool $active = false;
}

final class PushTest extends TestCase
{
	public function testPushSerializesModifiedBuiltinValues(): void
	{
		// Verifies push statements convert typed model values into query parameters.
		$model = new PushTestRecord();
		$model->setName('Pushed');
		$model->setActive(true);

		$sql = (string) new Create(PushTestRecord::class, $model);

		$this->assertStringContainsString('name_0 => Pushed', $sql);
		$this->assertStringContainsString('active_1 => 1', $sql);
	}

	public function testPushOmitsAutoIncrementIdentityColumn(): void
	{
		// Verifies push statements do not write auto-increment identity columns.
		$model = new PushTestRecord();
		$model->id = 10;
		$model->setName('Pushed');

		$sql = (string) new Create(PushTestRecord::class, $model);

		$this->assertStringNotContainsString('`id`', $sql);
		$this->assertStringContainsString('`name`', $sql);
	}
}
