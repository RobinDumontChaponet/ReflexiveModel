<?php

namespace Reflexive\Model;

use Reflexive\Core\{Database as DB, Comparator};
use Reflexive\Query;

// implements SCRUDInterface
abstract class ModelDAO
{
	const TABLE_NAME = '';
	protected static $className = 'Member';
	public static $database;

	const dateTime = 'Y-m-d H:i:s';

	protected static function getTableName(): string
	{
		$cc = get_called_class();

		return '`'.$cc::TABLE_NAME.'`';
	}

	protected static function getInstance(): ?\PDO
	{
		return self::$database;
	}

	protected static function beginTransaction(): bool
	{
		return self::getInstance()->beginTransaction();
	}

	protected static function commit(): bool
	{
		return self::getInstance()->commit();
	}

	protected static function rollBack(): bool
	{
		return self::getInstance()->rollBack();
	}

	protected static function lastInsertId(string $name = null): string
	{
		return self::getInstance()->lastInsertId($name);
	}

	protected static function prepare(string $statement): \PDOStatement
	{
		return self::getInstance()->prepare($statement);
	}

	static function delete($object): ModelStatement
	{
		return new DeleteModelStatement($object);
	}

	/* protected static function _search(array $on, string $combinator = 'OR', int $limit = null, int $offset = null): array
	{
		$statement = self::prepare('SELECT * FROM '.self::getTableName());
		$statement->setLimit($limit);
		$statement->setOffset($offset);

		$statement->setCombinator($combinator);
		$statement->autoBindClause(':id', @$on['id'], 'id = :id');

		$statement->execute();

		while ($rs = $statement->getStatement()->fetch(PDO::FETCH_OBJ)) {
			$objects[$rs->id] = new static::$className();
			$objects[$rs->id]->setId($rs->id);
		}

		return $objects;
	} */

	static function search(array $on, Query\Operator $combinator = Query\Operator::OR, int $limit = null, int $offset = null): Collection
	{
		$query = Query::select()
			->from(self::getTableName())
			->limit($limit)
			->offset($offset);

		foreach($on as $key => $value) {
			if($combinator == Query\Operator::OR)
				$query->or($key, Comparator::EQUAL, $value);
			else
				$query->and($key, Comparator::EQUAL, $value);
		}

		$query->prepare(self::getInstance());
		return new Collection(
			$query,
			function($rs) {
				$object = new static::$className();
				$object->setId($rs->id);

				return [$rs->id, $object];
			}
		);
	}

	static function count(): ?int
	{
		$statement = Query\Composed::count(
			'* AS c'
		)
			->from(self::getTableName())
			->prepare(self::getInstance());

		$statement->execute();

		if ($rs = $statement->fetch(\PDO::FETCH_OBJ)) {
			return $rs->c;
		}
	}

	public static function mysqlDateTime(\DateTime $dateTime = null): ?string
	{
		if($dateTime !== null)
			return $dateTime->format('Y-m-d H:i:s');

		return null;
	}

	public static function mysqlDate(\Date $date = null): ?string
	{
		if($date !== null)
			return $date->format('Y-m-d');

		return null;
	}
}
