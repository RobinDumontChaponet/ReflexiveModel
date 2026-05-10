<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Core\Comparator;
use Reflexive\Core\Database;
use Reflexive\Model\Column;
use Reflexive\Model\Model;
use Reflexive\Model\ModelCollection;
use Reflexive\Model\ModelId;
use Reflexive\Model\Property;
use Reflexive\Model\Table;

#[Table('search_test_records')]
final class SearchTestRecord extends Model
{
	use ModelId;

	#[Property]
	#[Column('name')]
	protected string $name = '';

	#[Property]
	#[Column('active')]
	protected bool $active = false;
}

final class SearchTest extends TestCase
{
	private Database $database;

	protected function setUp(): void
	{
		$this->database = new Database('sqlite::memory:');
		$this->database->exec('CREATE TABLE search_test_records (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, active INTEGER NOT NULL)');
		$this->database->exec("INSERT INTO search_test_records (name, active) VALUES ('First', 1), ('Second', 0), ('Third', 1)");
	}

	public function testSearchReturnsModelCollectionForMatchingRows(): void
	{
		// Verifies search returns a model collection that iterates hydrated rows.
		$collection = SearchTestRecord::search('active', Comparator::EQUAL, true)->execute($this->database);

		$this->assertInstanceOf(ModelCollection::class, $collection);
		$this->assertCount(2, $collection);
		$this->assertSame('First', $collection['1']->getName());
		$this->assertSame('Third', $collection['3']->getName());
	}

	public function testSearchStringIncludesWhereLimitAndOffset(): void
	{
		// Verifies search builds a select query with filters and paging.
		$sql = (string) SearchTestRecord::search('active', Comparator::EQUAL, true)->limit(5)->offset(1);

		$this->assertStringContainsString('SELECT `search_test_records`.`id`, `search_test_records`.`name`, `search_test_records`.`active`', $sql);
		$this->assertStringContainsString('WHERE `search_test_records`.`active` = :search_test_recordsactive_0', $sql);
		$this->assertStringContainsString('LIMIT 5 OFFSET 1', $sql);
	}
}
