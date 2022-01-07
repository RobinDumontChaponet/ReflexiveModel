<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Query;

class Read extends Pull
{
	public function execute(\PDO $database)
	{
		$statement = parent::_execute($database);
		$statement->execute();

		if($rs = $statement->fetch(\PDO::FETCH_OBJ)) {
			return self::$instanciators[$this->modelClassName]($rs)[1];
		}

		return null;
	}
}
