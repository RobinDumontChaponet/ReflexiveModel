<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Query;
use Reflexive\Core\Comparator;

class Update extends Push
{
	public function __construct(string $modelClassName, Model &$model)
	{
		$this->query = new Query\Update();
		// $this->where('id', Comparator::EQUAL, $model->getModelId());

		parent::__construct($modelClassName, $model);

		if(isset($this->schema)) {
			$modelId = $this->model->getModelId();
			$uids = $this->schema->getUIdColumnName();
			if(is_array($uids) && count($uids) > 1) {
				$value = $modelId[$uids[0]];
				if(is_object($value) && enum_exists($value::class))
					$value = $value->name;

				$this->query->where($uids[0], Comparator::EQUAL, $value);

				for ($i = 1; $i < count($uids); $i++) {
					$value = $modelId[$uids[$i]];
					if(is_object($value) && enum_exists($value::class))
						$value = $value->name;

					$this->query->and($uids[$i], Comparator::EQUAL, $value);
				}
			} else {
				$id = $model->getModelId();
				if(is_array($id))
					$id = array_values($id)[0];

				$this->where($this->schema->getUIdColumnNameString(), Comparator::EQUAL, $id);
			}

			// $this->query->where($this->schema->getUIdColumnNameString(), Comparator::EQUAL, $this->model->getModelId());
		}
	}

	public function execute(\PDO $database): bool
	{
		if(($superType = $this->schema->getSuperType()) !== null && ($superTypeSchema = Schema::getSchema($superType))) { // is subType of $superType
			if(!$superType::update($this->model)->execute($database))
				return false;

			foreach($superTypeSchema->getUIdColumnName() as $uid){
				if($superTypeSchema->isColumnAutoIncremented($uid)) {
					/** @psalm-suppress UndefinedMethod */
					$this->query->set($uid, $this->model->$uid);
				}
			}
		}

		$this->constructOuterReferences();

		//empty($this->model->getModifiedPropertiesNames())
		$execute = (!$this->model->updateUnmodified && empty($this->query->getSets())) ? null : parent::execute($database);

		foreach($this->referencedQueries as $referencedQuery) { // TODO : this is temporary
			if($referencedQuery instanceof Query\Composed)
				$execute = $referencedQuery->prepare($database)->execute();
			elseif($referencedQuery instanceof ModelStatement) {
				if($this->model->updateReferences)
					$execute = $referencedQuery->execute($database);
			}
		}

		if($execute)
			$this->model->resetModifiedPropertiesNames();

		return $execute ?? false;
	}
}
