<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Query;

class Create extends Push
{
	public function __construct(Model &$model)
	{
		$model->ignoreModifiedProperties = true;

		parent::__construct($model);
		$this->query = new Query\Insert();
	}

	public function execute(\PDO $database)
	{
		parent::execute($database);

		$this->model->setId((int)$database->lastInsertId());
	}
}
