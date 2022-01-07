<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ModelProperty
{
	public function __construct(
		public readonly ?string $columnName = null,
		public readonly bool $id = false,
		public readonly bool $unique = false,
		public readonly bool|string $makeGetter = true,
		public readonly bool|string $makeSetter = true,
		public readonly ?string $type = null,
		public readonly bool $nullable = false,
		public readonly bool $autoIncrement = false,
		public readonly ?string $arrayOf = null,
	)
	{}
}

/*

- columnName : bind to column in DB ;
- id : is ID ;
- unique : is unique ;
- makeGetter : should model respond to call of get{PropertyName} ; If string provided, use that as function name ; If set to false, let user defined function be used ;
- makeSetter : should model respond to call of set{PropertyName} ; If string provided, use that as function name ; If set to false, let user defined function be used ;
- type : type in DB ; Infer from PHP type if not set ;
- nullable : is nullable ;
- autoIncrement : is autoIncremented in DB ;
- arrayOf : is arrayOf in PHP ;

*/
