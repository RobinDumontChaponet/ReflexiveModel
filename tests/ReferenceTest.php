<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Model\Cardinality;
use Reflexive\Model\Reference;

final class ReferenceTest extends TestCase
{
	public function testConstructorStoresReferenceMetadata(): void
	{
		// Verifies reference attributes expose relationship metadata unchanged.
		$reference = new Reference(
			Cardinality::ManyToMany,
			type: 'Tag',
			nullable: true,
			columnName: 'post_id',
			foreignColumnName: 'left_id',
			foreignTableName: 'post_tags',
			foreignRightTableName: 'tags',
			foreignRightColumnName: 'right_id',
		);

		$this->assertSame(Cardinality::ManyToMany, $reference->cardinality);
		$this->assertSame('Tag', $reference->type);
		$this->assertTrue($reference->nullable);
		$this->assertSame('post_id', $reference->columnName);
		$this->assertSame('left_id', $reference->foreignColumnName);
		$this->assertSame('post_tags', $reference->foreignTableName);
		$this->assertSame('tags', $reference->foreignRightTableName);
		$this->assertSame('right_id', $reference->foreignRightColumnName);
	}
}
