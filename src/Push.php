<?php

declare(strict_types=1);

namespace Reflexive\Model;

abstract class Push extends ModelStatement
{
	public function __construct(
		protected Model &$model
	)
	{
		parent::__construct($model::class);
	}

	public function execute(\PDO $database)
	{
		$modifiedPropertiesNames = $this->model->getModifiedPropertiesNames();
		if(!$this->model->updateUnmodified && empty($modifiedPropertiesNames))
			return false;

		$statement = $this->query->prepare($database);
		$statement->execute();

		$this->model->setId((int)$database->lastInsertId());

		$this->model->resetModifiedPropertiesNames();
	}
}
