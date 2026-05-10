<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Model\Model;
use Reflexive\Model\ModelCollection;
use Reflexive\Model\ModelId;
use Reflexive\Model\Table;

#[Table('collection_test_items')]
final class CollectionTestItem extends Model
{
	use ModelId;
}

final class ModelCollectionTest extends TestCase
{
	public function testArrayAccessAddsObjectsAndTracksAddedKeys(): void
	{
		// Verifies array writes store models, update count, and track added keys.
		$item = new CollectionTestItem();
		$item->id = 7;

		$collection = new ModelCollection(CollectionTestItem::class);
		$collection['7'] = $item;

		$this->assertSame($item, $collection['7']);
		$this->assertSame(['7'], $collection->getAddedKeys());
		$this->assertSame(1, $collection->getModifiedCount());
		$this->assertCount(1, $collection);
	}

	public function testHasUsesModelIdentityString(): void
	{
		// Verifies membership checks use the model id string as collection key.
		$item = new CollectionTestItem();
		$item->id = 7;

		$collection = new ModelCollection(CollectionTestItem::class);
		$collection['7'] = $item;

		$this->assertTrue($collection->has($item));
	}
}
