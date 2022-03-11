<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class SchemaAttribute
{
	public function __construct(
		public readonly ?bool $useModelNames = true,
	)
	{}
}
