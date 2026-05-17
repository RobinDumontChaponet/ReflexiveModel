<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Core\Database;
use Reflexive\Model\Cardinality;
use Reflexive\Model\Column;
use Reflexive\Model\Hydrator;
use Reflexive\Model\Model;
use Reflexive\Model\ModelId;
use Reflexive\Model\Property;
use Reflexive\Model\Reference;
use Reflexive\Model\SCRUDInterface;
use Reflexive\Model\Table;

#[Table('hydrator_test_records')]
final class HydratorTestRecord extends Model
{
	use ModelId;

	#[Property]
	#[Column('name')]
	protected string $name = '';

	#[Property]
	#[Column('payload', type: 'json')]
	protected array $payload = [];
}

#[Table('hydrator_test_authors')]
final class HydratorTestAuthor extends Model
{
	use ModelId;

	#[Property]
	#[Column('name')]
	protected string $name = '';
}

#[Table('hydrator_test_articles')]
final class HydratorTestArticle extends Model
{
	use ModelId;

	#[Property]
	#[Column('title')]
	protected string $title = '';

	#[Property]
	#[Column('author_id')]
	#[Reference(Cardinality::OneToMany, type: HydratorTestAuthor::class, columnName: 'author_id')]
	protected HydratorTestAuthor $author;
}

#[Table('hydrator_test_nullable_articles')]
final class HydratorTestNullableArticle extends Model
{
	use ModelId;

	#[Property]
	#[Column('title')]
	protected string $title = '';

	#[Property]
	#[Column('author_id', nullable: true)]
	#[Reference(Cardinality::OneToMany, type: HydratorTestAuthor::class, columnName: 'author_id', nullable: true)]
	protected ?HydratorTestAuthor $author = null;
}

#[Table('hydrator_test_states')]
enum HydratorTestState implements SCRUDInterface
{
	use \Reflexive\Model\ModelEnum;

	case Ready;
	case Done;
}

final class HydratorTest extends TestCase
{
	public function testGetHydratorReturnsSameInstancePerModelClass(): void
	{
		// Verifies hydrators are cached per model class.
		$this->assertSame(
			Hydrator::getHydrator(HydratorTestRecord::class),
			Hydrator::getHydrator(HydratorTestRecord::class),
		);
	}

	public function testFetchHydratesModelColumnsAndCachesById(): void
	{
		// Verifies rows hydrate typed model properties and reuse cached identities.
		$hydrator = Hydrator::getHydrator(HydratorTestRecord::class);
		$row = (object) [
			'id' => 14,
			'name' => 'Hydrated',
			'payload' => '{"a":1}',
		];

		[$key, $model] = $hydrator->fetch($row, null);
		[, $cached] = $hydrator->fetch($row, null);

		$this->assertSame('14', $key);
		$this->assertInstanceOf(HydratorTestRecord::class, $model);
		$this->assertSame('Hydrated', $model->getName());
		$this->assertSame(['a' => 1], $model->getPayload());
		$this->assertSame($model, $cached);
	}

	public function testFetchHydratesEnumCase(): void
	{
		// Verifies enum-backed models hydrate from their id column.
		[$key, $case] = Hydrator::getHydrator(HydratorTestState::class)->fetch((object) ['id' => 'Done'], null);

		$this->assertSame('Done', $key);
		$this->assertSame(HydratorTestState::Done, $case);
	}

	public function testLazyFetchDoesNotInitializeGhostWhenCachingIdentity(): void
	{
		// Verifies identity-map storage does not force lazy ghost initialization.
		$row = (object) [
			'id' => 114,
			'name' => 'Lazy',
			'payload' => '{"lazy":true}',
		];

		[$key, $model] = Hydrator::getHydrator(HydratorTestRecord::class)->fetch($row, null, true);
		[, $cached] = Hydrator::getHydrator(HydratorTestRecord::class)->fetch($row, null, true);

		$this->assertSame('114', $key);
		$this->assertSame($model, $cached);
		$this->assertTrue((new \ReflectionObject($model))->isUninitializedLazyObject($model));
		$this->assertSame('Lazy', $model->getName());
		$this->assertFalse((new \ReflectionObject($model))->isUninitializedLazyObject($model));
	}

	public function testReferenceHydrationAssignsLazyGhostWithoutQueryingReference(): void
	{
		// Verifies single-object references remain lazy until the reference is accessed.
		$database = $this->makeReferenceDatabase();
		$database->exec("INSERT INTO hydrator_test_authors (name) VALUES ('Ada')");

		[, $article] = Hydrator::getHydrator(HydratorTestArticle::class)->fetch((object) [
			'id' => 7,
			'title' => 'Deferred',
			'author_id' => 1,
		], $database);

		$author = $article->getAuthor();

		$this->assertInstanceOf(HydratorTestAuthor::class, $author);
		$this->assertTrue((new \ReflectionObject($author))->isUninitializedLazyObject($author));
		$this->assertSame('Ada', $author->getName());
		$this->assertFalse((new \ReflectionObject($author))->isUninitializedLazyObject($author));
	}

	public function testNullableReferenceHydratesNullWithoutEagerQuery(): void
	{
		// Verifies nullable missing references become null instead of triggering eager loading.
		[, $article] = Hydrator::getHydrator(HydratorTestNullableArticle::class)->fetch((object) [
			'id' => 8,
			'title' => 'No author',
			'author_id' => null,
		], $this->makeReferenceDatabase());

		$this->assertNull($article->getAuthor());
	}

	private function makeReferenceDatabase(): Database
	{
		$database = new Database('sqlite::memory:');
		$database->exec('CREATE TABLE hydrator_test_authors (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
		$database->exec('CREATE TABLE hydrator_test_articles (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, author_id INTEGER NULL)');
		$database->exec('CREATE TABLE hydrator_test_nullable_articles (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, author_id INTEGER NULL)');

		return $database;
	}
}
