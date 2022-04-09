<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Query;

class Create extends Push
{
	public function __construct(Model &$model)
	{
		$model->ignoreModifiedProperties = true;
		$this->query = new Query\Insert();

		parent::__construct($model);
	}

	public function execute(\PDO $database)
	{
		$execute = parent::execute($database);

		if($execute) {
			$this->model->setId((int)$database->lastInsertId());

			foreach($this->referencedQueries as $referencedQuery) { // TODO : this is temporary
				if($referencedQuery instanceof Query\Composed)
					$referencedQuery->prepare($database)->execute();
				elseif($referencedQuery instanceof  ModelStatement) {
					if($this->model->updateReferences)
						$referencedQuery->execute($database);
				}
			}
		}

		return $execute;
	}
}
