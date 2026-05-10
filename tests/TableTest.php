<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Model\Table;

final class TableTest extends TestCase
{
	public function testConstructorStoresTableOptions(): void
	{
		// Verifies table attributes expose inheritance and naming options.
		$table = new Table('users', inheritColumns: false, isSuperType: true, useModelNames: false, subTypes: ['Admin']);

		$this->assertSame('users', $table->tableName);
		$this->assertFalse($table->inheritColumns);
		$this->assertTrue($table->isSuperType);
		$this->assertFalse($table->isSubType);
		$this->assertFalse($table->useModelNames);
		$this->assertSame(['Admin'], $table->subTypes);
	}
}
