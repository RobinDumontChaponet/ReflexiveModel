<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Attribute;
use Reflexive\Query\Simple;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Reference
{
	public function __construct(
		public readonly Simple $query,
	)
	{}
}
