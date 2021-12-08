<?php

namespace Reflexive\Core;

use Reflexive\Core\Database as DB;

abstract class ModelDAO implements SCRUDInterface
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

	static function delete($object)
	{
		$statement = self::prepare('DELETE FROM '.self::getTableName().' WHERE id=?');
		$statement->bindValue(1, $object->getId());
		$statement->execute();

		return $statement->rowCount();
	}

	protected static function _search(array $on, string $combinator = 'OR', int $limit = null, int $offset = null): array
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
	}

	static function search(array $on, string $combinator = 'OR', int $limit = null, int $offset = null): array
	{
		$objects = array();

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
	}

	static function count(): ?int
	{
		$statement = self::prepare('SELECT COUNT(*) AS c FROM '.self::getTableName());
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
