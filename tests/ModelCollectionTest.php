<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Model\Model;
use Reflexive\Model\ModelCollection;
use Reflexive\Model\ModelId;
use Reflexive\Model\ModelEnum;
use Reflexive\Model\SCRUDInterface;
use Reflexive\Model\Table;

#[Table('collection_test_items')]
final class CollectionTestItem extends Model
{
	use ModelId;
}

#[Table('collection_test_enums')]
enum CollectionTestEnum implements SCRUDInterface
{
	use ModelEnum;

	case First;
	case Second;
}

final class ModelCollectionTest extends TestCase
{
	public function testEmptyCollectionHasStableIteratorState(): void
	{
		// Verifies an empty collection can be inspected before iteration starts.
		$collection = new ModelCollection(CollectionTestItem::class);

		$this->assertFalse($collection->valid());
		$this->assertNull($collection->key());
		$this->assertNull($collection->current());
	}

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

	public function testArrayAccessReplacesExistingObjectAtOffset(): void
	{
		// Verifies assigning an existing offset updates the stored object.
		$original = new CollectionTestItem();
		$original->id = 7;
		$replacement = new CollectionTestItem();
		$replacement->id = 7;

		$collection = new ModelCollection(CollectionTestItem::class);
		$this->seedExistingObjects($collection, ['7' => $original]);
		$collection['7'] = $replacement;

		$this->assertSame($replacement, $collection['7']);
	}

	public function testArrayAccessMarksExistingModifiedObject(): void
	{
		// Verifies reassigning a modified existing model tracks it as modified.
		$item = new CollectionTestItem();
		$item->id = 7;
		$item->resetModifiedPropertiesNames();
		$item->id = 8;

		$collection = new ModelCollection(CollectionTestItem::class);
		$this->seedExistingObjects($collection, ['7' => $item]);
		$collection['7'] = $item;

		$this->assertSame(['7'], $collection->getModifiedKeys());
	}

	public function testUnsettingNewItemRemovesItFromAddedKeys(): void
	{
		// Verifies removing an unsaved collection item cancels its added-key tracking.
		$item = new CollectionTestItem();
		$item->id = 7;

		$collection = new ModelCollection(CollectionTestItem::class);
		$collection['7'] = $item;
		unset($collection['7']);

		$this->assertSame([], $collection->getAddedKeys());
		$this->assertSame([], $collection->getRemovedKeys());
		$this->assertSame(0, $collection->getModifiedCount());
		$this->assertCount(0, $collection);
	}

	public function testUnsettingEquivalentNumericKeyClearsCachedKey(): void
	{
		// Verifies int and numeric-string keys do not leave stale cache indexes.
		$first = new CollectionTestItem();
		$first->id = 1;
		$second = new CollectionTestItem();
		$second->id = 2;

		$collection = new ModelCollection(CollectionTestItem::class);
		$this->seedExistingObjects($collection, ['1' => $first, '2' => $second]);
		unset($collection[2]);

		$remaining = [];
		foreach($collection as $key => $item) {
			$remaining[$key] = $item;
		}

		$this->assertSame(['1' => $first], $remaining);
		$this->assertSame(['2'], $collection->getRemovedKeys());
	}

	public function testUnsetAllTracksEveryKnownKey(): void
	{
		// Verifies bulk removal of existing items keeps every tracked key.
		$first = new CollectionTestItem();
		$first->id = 1;
		$second = new CollectionTestItem();
		$second->id = 2;

		$collection = new ModelCollection(CollectionTestItem::class);
		$this->seedExistingObjects($collection, ['1' => $first, '2' => $second]);

		$this->assertSame(2, $collection->unsetAll());
		$this->assertSame(['1', '2'], array_values($collection->getRemovedKeys()));
	}

	public function testUnsetAllDropsNewItemsWithoutMarkingThemRemoved(): void
	{
		// Verifies bulk removal cancels pending additions rather than removing unsaved rows.
		$first = new CollectionTestItem();
		$first->id = 1;
		$second = new CollectionTestItem();
		$second->id = 2;

		$collection = new ModelCollection(CollectionTestItem::class);
		$collection['1'] = $first;
		$collection['2'] = $second;

		$this->assertSame(2, $collection->unsetAll());
		$this->assertSame([], $collection->getAddedKeys());
		$this->assertSame([], $collection->getRemovedKeys());
		$this->assertSame(0, $collection->getModifiedCount());
	}

	public function testEnumHasDoesNotChangeCollectionCount(): void
	{
		// Verifies enum membership checks do not mutate the collection count.
		$collection = new ModelCollection(CollectionTestEnum::class);
		$collection[CollectionTestEnum::First->name] = CollectionTestEnum::First;

		$this->assertCount(1, $collection);
		$this->assertFalse($collection->has(CollectionTestEnum::Second));
		$this->assertCount(1, $collection);
	}

	private function seedExistingObjects(ModelCollection $collection, array $objects): void
	{
		$objectReflection = new \ReflectionObject($collection);

		$objectsProperty = $objectReflection->getProperty('objects');
		$objectsProperty->setValue($collection, $objects);

		$keysProperty = $objectReflection->getProperty('keys');
		$keysProperty->setValue($collection, array_map('strval', array_keys($objects)));

		$countProperty = $objectReflection->getProperty('count');
		$countProperty->setValue($collection, count($objects));
	}
}
