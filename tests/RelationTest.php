<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Model\Relation;

final class RelationTest extends TestCase
{
	public function testConstructorStoresRelationTableName(): void
	{
		// Verifies relation attributes expose the configured table name.
		$relation = new Relation('users_have_roles');

		$this->assertSame('users_have_roles', $relation->tableName);
	}
}
