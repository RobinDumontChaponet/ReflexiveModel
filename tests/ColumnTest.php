<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Model\Column;

final class ColumnTest extends TestCase
{
	public function testConstructorStoresColumnMetadata(): void
	{
		// Verifies column attributes expose constructor metadata unchanged.
		$column = new Column('user_id', isId: true, unique: true, type: 'BIGINT', nullable: false, autoIncrement: true, defaultValue: 12);

		$this->assertSame('user_id', $column->name);
		$this->assertTrue($column->isId);
		$this->assertTrue($column->unique);
		$this->assertSame('BIGINT', $column->type);
		$this->assertFalse($column->nullable);
		$this->assertTrue($column->autoIncrement);
		$this->assertSame(12, $column->defaultValue);
	}
}
