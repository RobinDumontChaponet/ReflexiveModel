<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Core\Database;
use Reflexive\Model\Column;
use Reflexive\Model\Model;
use Reflexive\Model\ModelCollection;
use Reflexive\Model\ModelId;
use Reflexive\Model\ModelEnum;
use Reflexive\Model\Property;
use Reflexive\Model\SCRUDInterface;
use Reflexive\Model\Table;

#[Table('collection_test_items')]
final class CollectionTestItem extends Model
{
	use ModelId;

	#[Property]
	#[Column('name')]
	protected string $name = '';
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

	public function testIteratorIncludesManuallyAddedObjects(): void
	{
		// Verifies array-added objects participate in normal collection iteration.
		$item = new CollectionTestItem();
		$item->id = 7;

		$collection = new ModelCollection(CollectionTestItem::class);
		$collection['7'] = $item;

		$this->assertSame([7 => $item], iterator_to_array($collection));
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
		$this->assertCount(0, $collection);
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

	public function testPushPersistsAddedModifiedAndRemovedModels(): void
	{
		// Verifies push syncs tracked creations, updates, and deletions to the database.
		$database = $this->makeDatabase();
		$database->exec("INSERT INTO collection_test_items (name) VALUES ('old'), ('remove')");

		$existing = new CollectionTestItem();
		$existing->id = 1;
		$existing->setName('old');
		$existing->resetModifiedPropertiesNames();
		$existing->setName('updated');
		$removed = new CollectionTestItem();
		$removed->id = 2;
		$removed->setName('remove');
		$new = new CollectionTestItem();
		$new->setName('created');

		$collection = new ModelCollection(CollectionTestItem::class);
		$this->seedExistingObjects($collection, ['1' => $existing, '2' => $removed]);
		$collection['1'] = $existing;
		unset($collection['2']);
		$collection[] = $new;

		$this->assertSame(['created' => 1, 'updated' => 1, 'deleted' => 1], $collection->push($database));
		$this->assertSame(
			['updated', 'created'],
			$database->query('SELECT name FROM collection_test_items ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN),
		);
		$this->assertSame([1, 3], array_keys($collection->asArray(false)));
		$this->assertSame([], $collection->getAddedKeys());
		$this->assertSame([], $collection->getModifiedKeys());
		$this->assertSame([], $collection->getRemovedKeys());
	}

	public function testPushPersistsBulkRemovedModels(): void
	{
		// Verifies push deletes every persisted model removed by unsetAll.
		$database = $this->makeDatabase();
		$database->exec("INSERT INTO collection_test_items (name) VALUES ('first'), ('second')");

		$first = new CollectionTestItem();
		$first->id = 1;
		$first->setName('first');
		$second = new CollectionTestItem();
		$second->id = 2;
		$second->setName('second');

		$collection = new ModelCollection(CollectionTestItem::class);
		$this->seedExistingObjects($collection, ['1' => $first, '2' => $second]);
		$collection->unsetAll();

		$this->assertSame(['created' => 0, 'updated' => 0, 'deleted' => 2], $collection->push($database));
		$this->assertSame(0, (int)$database->query('SELECT COUNT(*) FROM collection_test_items')->fetchColumn());
		$this->assertSame([], $collection->asArray(false));
		$this->assertCount(0, $collection);
		$this->assertSame([], $collection->getRemovedKeys());
	}

	public function testPushPersistsModelsModifiedDuringIteration(): void
	{
		// Verifies foreach mutations are detected without reassigning collection offsets.
		$database = $this->makeDatabase();
		$database->exec("INSERT INTO collection_test_items (name) VALUES ('old')");

		$item = new CollectionTestItem();
		$item->id = 1;
		$item->setName('old');
		$item->resetModifiedPropertiesNames();

		$collection = new ModelCollection(CollectionTestItem::class);
		$this->seedExistingObjects($collection, ['1' => $item]);
		foreach($collection as $model) {
			$model->setName('updated');
		}

		$this->assertSame([], $collection->getModifiedKeys());
		$this->assertSame(['created' => 0, 'updated' => 1, 'deleted' => 0], $collection->push($database));
		$this->assertSame('updated', $database->query('SELECT name FROM collection_test_items WHERE id = 1')->fetchColumn());
		$this->assertSame([], $collection->getModifiedKeys());
	}

	public function testPushReturnsZeroCountsWhenThereAreNoChanges(): void
	{
		// Verifies push is a no-op when the collection has no tracked changes.
		$database = $this->makeDatabase();
		$collection = new ModelCollection(CollectionTestItem::class);

		$this->assertSame(['created' => 0, 'updated' => 0, 'deleted' => 0], $collection->push($database));
	}

	public function testPushRejectsEnumCollections(): void
	{
		// Verifies push only operates on model collections with database-backed models.
		$this->expectException(\LogicException::class);

		(new ModelCollection(CollectionTestEnum::class))->push($this->makeDatabase());
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

	private function makeDatabase(): Database
	{
		$database = new Database('sqlite::memory:');
		$database->exec('CREATE TABLE collection_test_items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');

		return $database;
	}
}
