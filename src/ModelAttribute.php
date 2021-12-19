<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Attribute;

// Attribute::TARGET_CLASS
// Attribute::TARGET_FUNCTION
// Attribute::TARGET_METHOD
// Attribute::TARGET_PROPERTY
// Attribute::TARGET_CLASS_CONSTANT
// Attribute::TARGET_PARAMETER
// Attribute::TARGET_ALL

#[Attribute(Attribute::TARGET_CLASS)]
class ModelAttribute
{
	// const VALUE = 'value';

	public function __construct(
		public readonly string $tableName,
	)
	{}
}
