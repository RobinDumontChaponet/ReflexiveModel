<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ModelProperty
{
	public function __construct(
		public readonly bool|string $makeGetter = true,
		public readonly bool|string $makeSetter = true,
		public readonly ?string $arrayOf = null,
	)
	{}
}

/*

- makeGetter : should model respond to call of get{PropertyName} ; If string provided, use that as function name ; If set to false, let user defined function be used ;
- makeSetter : should model respond to call of set{PropertyName} ; If string provided, use that as function name ; If set to false, let user defined function be used ;

*/
