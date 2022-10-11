<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Table
{
	public function __construct(
		public readonly ?string $tableName = null,
		public readonly bool $inheritColumns = true,
		public readonly ?bool $useModelNames = true,
	)
	{}
}
