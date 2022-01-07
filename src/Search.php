<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Query;

class Search extends Pull
{
	public function execute(\PDO $database)
	{
		return new Collection(
			parent::_execute($database),
			self::$instanciators[$this->modelClassName]
		);
	}
}
