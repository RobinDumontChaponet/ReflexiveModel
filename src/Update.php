<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Query;
use Reflexive\Core\Comparator;

class Update extends Push
{
	public function __construct(Model &$model)
	{
		$this->query = new Query\Update();
		// $this->where('id', Comparator::EQUAL, $model->getId());

		parent::__construct($model);

		if(isset($this->schema)) {
			$this->query->where($this->schema->getUIdColumnName(), Comparator::EQUAL, $this->model->getId());
		}
	}
}
