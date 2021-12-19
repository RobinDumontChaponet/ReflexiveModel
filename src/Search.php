<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Query;

class Search extends ModelStatement
{
	public function __construct(string $model)
	{
		parent::__construct($model);

		$this->query = new Query\Select();
		// $this->query->from($model::getTableName());
	}
}
