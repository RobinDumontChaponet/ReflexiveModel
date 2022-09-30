<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Query;
use Reflexive\Core\Comparator;

class Update extends Push
{
	public function __construct(Model &$model)
	{
		$this->query = new Query\Update();
		// $this->where('id', Comparator::EQUAL, $model->getId());

		parent::__construct($model);
		$this->constructOuterReferences();

		if(isset($this->schema)) {
			$this->query->where($this->schema->getUIdColumnNameString(), Comparator::EQUAL, $this->model->getId());
		}
	}

	public function execute(\PDO $database): bool
	{
		$execute = (!$this->model->updateUnmodified && empty($this->model->getModifiedPropertiesNames())) ? null : parent::execute($database);

		foreach($this->referencedQueries as $referencedQuery) { // TODO : this is temporary
			if($referencedQuery instanceof Query\Composed)
				$execute ??= $referencedQuery->prepare($database)->execute();
			elseif($referencedQuery instanceof  ModelStatement) {
				if($this->model->updateReferences)
					$execute ??= $referencedQuery->execute($database);
			}
		}

		if($execute)
			$this->model->resetModifiedPropertiesNames();

		return $execute ?? false;
	}
}
