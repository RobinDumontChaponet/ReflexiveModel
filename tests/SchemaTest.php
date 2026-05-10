<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Model\Cardinality;
use Reflexive\Model\Column;
use Reflexive\Model\Model;
use Reflexive\Model\ModelId;
use Reflexive\Model\Property;
use Reflexive\Model\Reference;
use Reflexive\Model\Schema;
use Reflexive\Model\Table;

enum SchemaTestStatus
{
	case Draft;
	case Published;
}

#[Table('schema_test_authors')]
final class SchemaTestAuthor extends Model
{
	use ModelId;

	#[Property(maxLength: 120)]
	#[Column('display_name')]
	protected string $name = '';
}

#[Table('schema_test_posts')]
final class SchemaTestPost extends Model
{
	use ModelId;

	#[Property(maxLength: 160)]
	#[Column('headline')]
	protected string $title = '';

	#[Property]
	#[Column]
	protected bool $published = false;

	#[Property]
	#[Column]
	protected SchemaTestStatus $status = SchemaTestStatus::Draft;

	#[Property]
	#[Reference(Cardinality::OneToMany, type: SchemaTestAuthor::class, columnName: 'author_id')]
	protected SchemaTestAuthor $author;
}

final class SchemaTest extends TestCase
{
	public function testSchemaInfersColumnsFromModelAttributes(): void
	{
		// Verifies table, column names, types, defaults, and id metadata are inferred.
		$schema = Schema::getSchema(SchemaTestPost::class);

		$this->assertNotNull($schema);
		$this->assertSame('schema_test_posts', $schema->getTableName());
		$this->assertSame(['id', 'title', 'published', 'status'], $schema->getPropertyNames());
		$this->assertSame('headline', $schema->getColumnName('title'));
		$this->assertSame('VARCHAR(160)', $schema->getColumnTypeString('title'));
		$this->assertSame('TINYINT(1)', $schema->getColumnTypeString('published'));
		$this->assertSame("ENUM('Draft','Published')", $schema->getColumnTypeString('status'));
		$this->assertSame(SchemaTestStatus::Draft, $schema->getColumnDefaultValue('status'));
		$this->assertSame(['id'], $schema->getUIdPropertyName());
	}

	public function testSchemaInfersReferenceMetadata(): void
	{
		// Verifies reference attributes resolve target schema and foreign-key metadata.
		$schema = Schema::getSchema(SchemaTestPost::class);

		$this->assertTrue($schema->hasReference('author'));
		$this->assertSame(Cardinality::OneToMany, $schema->getReferenceCardinality('author'));
		$this->assertSame(SchemaTestAuthor::class, $schema->getReferenceType('author'));
		$this->assertSame('author_id', $schema->getReferenceColumnName('author'));
		$this->assertFalse($schema->hasColumn('author'));
	}
}
