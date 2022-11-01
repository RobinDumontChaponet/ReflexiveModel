<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Core\Comparator;
use Reflexive\Query;

class Delete extends ModelStatement
{
	public function __construct(
		string $modelClassName,
		private Model $model
	)
	{
		parent::__construct($modelClassName);
		$this->query = new Query\Delete();
		$this->where('id', Comparator::EQUAL, $model->getModelId());
	}

	/*
	 * @throws \TypeError
	 */
	public function execute(\PDO $database): bool
	{
		$statement = parent::_prepare($database);
		return $statement->execute();
	}
}
