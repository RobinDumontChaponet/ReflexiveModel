<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Query;

class Search extends ModelStatement
{
	public function __construct(string $modelClassName)
	{
		parent::__construct($modelClassName);

		$this->query = new Query\Select();
		// $this->query->from($model::getTableName());
	}

	public function execute(\PDO $database)
	{
		return new Collection(
			parent::_execute($database),
			self::$generators[$this->modelClassName]
		);
	}
}
