<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Model\Property;

final class PropertyTest extends TestCase
{
	public function testConstructorStoresAccessorOptions(): void
	{
		// Verifies property attributes expose accessor and validation options.
		$property = new Property(makeGetter: 'title', makeSetter: false, maxLength: 42, readOnly: true);

		$this->assertSame('title', $property->makeGetter);
		$this->assertFalse($property->makeSetter);
		$this->assertSame(42, $property->maxLength);
		$this->assertTrue($property->readOnly);
	}
}
