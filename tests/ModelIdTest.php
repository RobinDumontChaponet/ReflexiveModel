<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Model\Model;
use Reflexive\Model\ModelId;
use Reflexive\Model\Schema;
use Reflexive\Model\Table;

#[Table('model_id_test_records')]
final class ModelIdTestRecord extends Model
{
	use ModelId;
}

final class ModelIdTest extends TestCase
{
	public function testTraitProvidesDefaultIdAndSchemaMetadata(): void
	{
		// Verifies ModelId adds a default id and auto-increment schema metadata.
		$model = new ModelIdTestRecord();
		$schema = Schema::getSchema(ModelIdTestRecord::class);

		$this->assertSame(['id' => -1], $model->getModelId());
		$this->assertSame('-1', $model->getModelIdString());
		$this->assertSame(['id'], $schema->getUIdPropertyName());
		$this->assertSame('id', $schema->getColumnName('id'));
		$this->assertTrue($schema->isColumnAutoIncremented('id'));
		$this->assertTrue($schema->isColumnUnique('id'));
	}

	public function testSetModelIdWritesProtectedIdProperty(): void
	{
		// Verifies positional model id assignment writes the trait's managed id property.
		$model = new ModelIdTestRecord();

		$model->setModelId(42);

		$this->assertSame(['id' => 42], $model->getModelId());
		$this->assertSame('42', $model->getModelIdString());
	}
}
