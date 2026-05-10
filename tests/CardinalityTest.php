<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Model\Cardinality;

final class CardinalityTest extends TestCase
{
	public function testEnumCasesExposeRelationshipLabels(): void
	{
		// Verifies cardinality cases keep their public string labels.
		$this->assertSame('one to one', Cardinality::OneToOne->value);
		$this->assertSame('one to many', Cardinality::OneToMany->value);
		$this->assertSame('many to one', Cardinality::ManyToOne->value);
		$this->assertSame('many to many', Cardinality::ManyToMany->value);
	}
}
