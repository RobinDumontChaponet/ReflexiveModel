<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Query;

class Update extends Push
{
	public function __construct(Model &$model)
	{
		parent::__construct($model);
		$this->query = new Query\Update();
		// $this->where('id', Comparator::EQUAL, $model->getId());
	}
}
