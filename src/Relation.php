<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Relation
{
	public function __construct(
		public readonly ?string $tableName = null,
	)
	{}
}
