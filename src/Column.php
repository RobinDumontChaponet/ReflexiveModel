<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY|Attribute::TARGET_METHOD)]
class Column
{
	public function __construct(
		public readonly ?string $name = null,
		public readonly bool $isId = false,
		public readonly bool $unique = false,
		public readonly ?string $type = null,
		public readonly bool|null $nullable = null,
		public readonly bool $autoIncrement = false,
		public readonly mixed $defaultValue = null,
		public readonly ?\Reflexive\Query\ColumnExtra $extra = null,
	)
	{}
}

/*

- name : bind to column in DB ;
- isId : is ID ;
- isUnique : is unique ;
- type : type in DB ; Infer from PHP type if not set ;
- isNullable : is nullable ;
- autoIncrement : is autoIncremented in DB ;

*/
