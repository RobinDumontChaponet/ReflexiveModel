<?php

declare(strict_types=1);

namespace Reflexive\Core;

use Attribute;

// Attribute::TARGET_CLASS
// Attribute::TARGET_FUNCTION
// Attribute::TARGET_METHOD
// Attribute::TARGET_PROPERTY
// Attribute::TARGET_CLASS_CONSTANT
// Attribute::TARGET_PARAMETER
// Attribute::TARGET_ALL

#[Attribute(Attribute::TARGET_PROPERTY)]
class ModelAttribute
{
	// const VALUE = 'value';

	public function __construct(
		public readonly string $columnName,
		public readonly bool|string $makeGetter = true,
		public readonly bool|string $makeSetter = true,
	)
	{}
}


/*

- columnName : bind to column in DB ;

*/
