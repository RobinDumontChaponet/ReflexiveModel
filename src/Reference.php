<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY|Attribute::TARGET_CLASS_CONSTANT)]
class Reference
{
	public function __construct(
		public readonly Cardinality $cardinality = Cardinality::OneToMany,
		public readonly ?string $type = null,
		public readonly bool $nullable = true,
		public readonly ?string $columnName = null,
		public readonly ?string $foreignColumnName = null,
		public readonly ?string $foreignTableName = null,
		public readonly ?string $foreignRightTableName = null,
		public readonly ?string $foreignRightColumnName = null,
		// public readonly bool $inverse = false,
	)
	{}
}
