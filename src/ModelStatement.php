<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Query;

use Psr\SimpleCache;

abstract class ModelStatement
{
	// internals
	protected Query\Composed $query;
	protected ?Schema $schema = null;

	protected ?string $groupedBy = null; // columnName to group by if any

	protected array $referencedStatements = [];

	// global caches ?
	public static bool $useInternalCache = true;
	public static ?SimpleCache\CacheInterface $cache = null;
	public static int $cacheTTL = 120;
	protected static array $models = [];

	protected function __construct(
		protected string $modelClassName,
	)
	{}

	protected function init(): void
	{
		$schema = $this->schema ?? Schema::getSchema($this->modelClassName);

		$this->schema = $schema;
		$this->query->from($this->schema->getTableName());

		// if(($superType = $this->schema->getSuperType()) !== null && ($superTypeSchema = Schema::getSchema($superType))) { // is subType of $superType
		// 	$this->query->join(
		// 		Query\Join::left,
		// 		$this->schema->getReferenceForeignTableName($superTypeSchema->getTableName()),
		// 		$this->schema->getReferenceForeignColumnName($superTypeSchema->getColumnName('id')),
		// 		Comparator::EQUAL,
		// 		$this->schema->getTableName(),
		// 		$this->schema->getUidColumnNameString(),
		// 	);
		// }
	}

	protected function _prepare(\PDO $database): \PDOStatement
	{
		$this->init();

		return $this->query->prepare($database);
	}

	public abstract function execute(\PDO $database);

	public function from(Schema $schema): static
	{
		$this->$schema = $schema;
		return $this;
	}

	public function where(Condition|ConditionGroup $condition): static
	{
		if($this->schema == null)
			$this->init();

		$baked = $condition->bake($this->schema);
		$this->query->where($baked['conditions'] ?? $baked['condition']);
		if(isset($baked['joins'])) {
			foreach($baked['joins'] as $join) {
				$this->query->join(...$join);
			}
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

	public function limit(?int $limit = null): static
	{
		$this->query->limit($limit);

		return $this;
	}
	public function offset(?int $offset = null): static
	{
		$this->query->offset($offset);

		return $this;
	}

	public function __toString(): string
	{
		return $this->getQuery()->__toString();
	}

	public function getQuery (): Query\Composed
	{
		$this->init();
		// $this->query->bake();

		return $this->query;
	}
}
