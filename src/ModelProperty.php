<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ModelProperty
{
	// const VALUE = 'value';

	public function __construct(
		public readonly string $columnName,
		public readonly bool $id = false,
		public readonly bool $unique = false,
		public readonly bool|string $makeGetter = true,
		public readonly bool|string $makeSetter = true,
		public readonly string $type = 'string',
		public readonly bool $nullable = false,
		public readonly bool $autoIncrement = false,
	)
	{}
}


/*

- columnName : bind to column in DB ;

*/
