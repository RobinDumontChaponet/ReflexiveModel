<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Core\Comparator;
use Reflexive\Query;
use ReflectionClass;
use ReflectionUnionType;
use ReflectionIntersectionType;

abstract class ModelStatement
{
	// some config. Not in use.
	// public bool $useAccessors = false;

	// internals
	protected Query\Composed $query;
	protected ?Schema $schema;

	protected array $referencedStatements = [];

	// global caches ?
	protected static array $instanciators = [];

	private ?\PDO $database;

	protected function __construct(
		protected string $modelClassName,
	)
	{}

	protected function initSchema() {
		if(!isset($this->schema)) {
			$schema = Schema::initFromAttributes($this->modelClassName);

			if(isset($schema) && !isset(self::$instanciators[$this->modelClassName])) {
				$classReflection = new ReflectionClass($this->modelClassName);

				// referenced models
				foreach($schema->getColumns() as $propertyName => $column) {
					if(isset($column['reference'])) {
						$reference = $column['reference'];
						// SELECT *
						// FROM inGroup
						// JOIN `User` ON User.id = inGroup.userId
						// JOIN `Group` ON Group.id = inGroup.groupId;

						// $search = new Search($reference['modelClassName']);
						// $this->referencedStatements[$reference['modelClassName']] = [
						// 	'statement' => $search,
						// 	'collection' => null,
						// ];
					}
				}

				// "instanciator" instantiate object without calling its constructor when needed by Collection or single pull
				self::$instanciators[$this->modelClassName] = function($rs) use ($classReflection, $schema) {
					$this->modelClassName::initModelAttributes();
					$object = $classReflection->newInstanceWithoutConstructor();
					$object->setId($rs->id);

					foreach($schema->getColumns() as $propertyName => $column) {
						if(isset($column['name'])) {
							$propertyReflexion = $classReflection->getProperty($propertyName);
							$propertyReflexion->setAccessible(true);

							if($type = $propertyReflexion->getType()) {
								if($types = $type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType ? $type->getTypes() : [$type]) {
									foreach($types as $type) {
										if($type->allowsNull() && (!isset($rs->{$column['name']}) || is_null($rs->{$column['name']}))) { // is null and nullable
											$propertyReflexion->setValue($object, null);
											break;
										} else {
											if($type->isBuiltin()) { // PHP builtin types
												$propertyReflexion->setValue($object, $rs->{$column['name']});
												break;
											} else {
												$propertyReflexion->setValue($object, match($type->getName()) {
													'DateTime' => new \DateTime($rs->{$column['name']}),
												});
												break;
											}
										}
									}
								} else {
									$propertyReflexion->setValue($object, $rs->{$column['name']});
								}
							}
						}

						if(isset($column['reference']) && isset($this->database)) {
							$propertyReflexion = $classReflection->getProperty($propertyName);
							$propertyReflexion->setAccessible(true);

							// var_dump($this->referencedStatements[$column['reference']['modelClassName']]);

							$collection = $column['reference']['modelClassName']::search($column['reference']['columnName'], Comparator::EQUAL, $rs->{$schema->getUIdColumnName()})->execute($this->database);

							$propertyReflexion->setValue($object, $collection);
						}
					}

					$_POST['count']++;

					return [$rs->id, $object];
				};
			}

			$this->schema = $schema;
			$this->query->from($this->schema->getTableName());
		}
		// else {
		// 	throw new \Exception('Could not infer instanciator from Model attributes.');
		// }
	}

	protected function _execute(\PDO $database): \PDOStatement
	{
		$this->initSchema();

		$this->database = $database;

		// foreach($this->referencedStatements as $className => $statement) {
		// 	var_dump($className);
		// 	$this->referencedStatements[$className]['collection'] = $statement['statement']->execute($database);
		// }

		return $this->query->prepare($database);
	}

	public abstract function execute(\PDO $database);

	public function from(Schema $schema): static
	{
		$this->$schema = $schema;
		return $this;
	}

	public function where(string $propertyName, Comparator $comparator, string|int|float|array|bool $value = null): static
	{
		$this->initSchema();

		if($this->schema->hasColumn($propertyName)) {
			if(is_bool($value))
				$value = (int)$value;

			$this->query->where($this->schema->getColumnName($propertyName), $comparator, $value);
		} else {
			throw new \TypeError('Property "'.$propertyName.'" not found in Schema "'.$this->schema->getTableName().'"');
		}

		return $this;
	}

	public function and(...$where): static
	{
		$this->query->and();
		$this->where(...$where);

		return $this;
	}

	public function or(...$where): static
	{
		$this->query->or();
		$this->where(...$where);

		return $this;
	}

	public function __toString(): string
	{
		return $this->query->__toString();
	}
}
