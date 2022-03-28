<?php

declare(strict_types=1);

namespace Reflexive\Model;

class Read extends Pull
{
	public function execute(\PDO $database)
	{
		$this->initSchema();

		if(isset($this->schema)) {
			// $this->query->setColumns($this->schema->getColumnNames());
		}

		$statement = $this->query->prepare($database);
		if($statement->execute()) {
			if($rs = $statement->fetch(\PDO::FETCH_OBJ)) {
				return self::$instanciators[$this->modelClassName](
					$rs,
					$database
				)[1];
			}
		}

		return null;
	}
}
