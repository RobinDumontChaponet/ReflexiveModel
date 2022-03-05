<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Core\Comparator;
use Reflexive\Query;

class Delete extends ModelStatement
{
	public function __construct(
		private Model $model
	)
	{
		parent::__construct($model::class);
		$this->query = new Query\Delete();
		$this->where('id', Comparator::EQUAL, $model->getId());
	}

	public function execute(\PDO $database)
	{
		$statement = parent::_prepare($database);
		$statement->execute();
	}
}
