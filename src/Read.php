<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Query;

class Read extends ModelStatement
{
	public function __construct(string $modelClassName)
	{
		parent::__construct($modelClassName);
		$this->query = new Query\Select();
	}

	public function execute(\PDO $database)
	{
		$statement = parent::_execute($database);
		$statement->execute();

		if($rs = $statement->fetch(\PDO::FETCH_OBJ)) {
			return self::$generators[$this->modelClassName]($rs)[1];
		}

		return null;
	}
}
