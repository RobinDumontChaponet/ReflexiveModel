<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Query;

class Create extends Push
{
	public function __construct(string $modelClassName, Model &$model)
	{
		$model->ignoreModifiedProperties = true;
		$this->query = new Query\Insert();

		parent::__construct($modelClassName, $model);
	}

	public function execute(\PDO $database): bool
	{
		$execute = parent::execute($database);

		if($execute) {
			foreach($this->schema->getUIdColumnName() as $uid){
				if($this->schema->isColumnAutoIncremented($uid))
					$this->model->$uid = (int)$database->lastInsertId();
			}

			$this->constructOuterReferences();

			foreach($this->referencedQueries as $referencedQuery) { // TODO : this is temporary
				if($referencedQuery instanceof Query\Composed)
					$referencedQuery->prepare($database)->execute();
				elseif($referencedQuery instanceof ModelStatement) {
					if($this->model->updateReferences)
						$referencedQuery->execute($database);
				}
			}
			$this->model->resetModifiedPropertiesNames();
		}

		return $execute;
	}
}
