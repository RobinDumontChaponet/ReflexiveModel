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

	public function execute(\PDO $database): int|array
	{
		$this->init();

		if($this->groupedBy) {
			$this->query->setColumns([
				'g' => $this->groupedBy,
				'c' => 'COUNT(*)',
			]);
		}

		$statement = $this->query->prepare($database);
		$statement->execute();

		if($this->groupedBy) {
			$array = [];
			while (($rs = $statement->fetch(\PDO::FETCH_OBJ)) !== false) {
				$array[$rs->g] = $rs->c; // get key from db ignoring property type, for now
			}
			return $array;
		} else {
			if($rs = $statement->fetch(\PDO::FETCH_OBJ)) {
				return $rs->c;
			}
		}

		return 0;
	}
}
