<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Query;

abstract class Pull extends ModelStatement
{
	public function __construct(string $modelClassName)
	{
		parent::__construct($modelClassName);
		$this->query = new Query\Select();
	}
}
