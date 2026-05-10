<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Model\ModelEnum;
use Reflexive\Model\Schema;
use Reflexive\Model\SCRUDInterface;
use Reflexive\Model\Table;

#[Table('enum_test_states')]
enum EnumTestState implements SCRUDInterface
{
	use ModelEnum;

	case Draft;
	case Published;
}

final class ModelEnumTest extends TestCase
{
	public function testEnumIdentityUsesCaseName(): void
	{
		// Verifies enum model identity is derived from the enum case name.
		$this->assertSame('Draft', EnumTestState::Draft->getModelId());
		$this->assertSame('Draft', EnumTestState::Draft->getModelIdString());
	}

	public function testEnumSchemaUsesCaseNameIdColumn(): void
	{
		// Verifies enum schemas expose a string id column sized to case names.
		$schema = Schema::getSchema(EnumTestState::class);

		$this->assertTrue($schema->isEnum());
		$this->assertSame('enum_test_states', $schema->getTableName());
		$this->assertSame('id', $schema->getUIdPropertyName());
		$this->assertSame('VARCHAR(9)', $schema->getColumnTypeString('id'));
	}
}
