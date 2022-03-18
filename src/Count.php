<?php

declare(strict_types=1);

namespace Reflexive\Model;

class Count extends Pull
{
	public function __construct(string $modelClassName)
	{
		parent::__construct($modelClassName);
		$this->query->setColumns(['c' => 'COUNT(*)']);
	}

	public function execute(\PDO $database)
	{
		$this->initSchema();

		$statement = $this->query->prepare($database);
		$statement->execute();

		if($rs = $statement->fetch(\PDO::FETCH_OBJ)) {
			return $rs->c;
		}

		return 0;
	}
}
