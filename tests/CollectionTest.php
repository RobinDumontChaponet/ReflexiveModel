<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Model\Collection;
use Reflexive\Model\Model;
use Reflexive\Model\ModelCollection;
use Reflexive\Model\ModelId;
use Reflexive\Model\Table;

#[Table('collection_contract_test_records')]
final class CollectionContractTestRecord extends Model
{
	use ModelId;
}

final class CollectionTest extends TestCase
{
	public function testModelCollectionImplementsCollectionContract(): void
	{
		// Verifies the concrete collection implements the package collection interface.
		$this->assertInstanceOf(Collection::class, new ModelCollection(CollectionContractTestRecord::class));
	}
}
