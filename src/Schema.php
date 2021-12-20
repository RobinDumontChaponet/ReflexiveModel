<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Core\Comparator;

class Schema implements \JsonSerializable
{
	/*
	 * $columns[propertyName] = columnName;
	 */
	protected array $columns = [];

	public function __construct(
		protected string $tableName,
	)
	{}

	public function hasColumn(int|string $key): bool
	{
		return isset($this->columns[$key]);
	}

	public function getColumnName(int|string $key): ?string
	{
		if($this->hasColumn($key))
			return $this->columns[$key]['name'];

		return null;
	}

	public function setColumnName(int|string $key, string $name): void
	{
		if($this->hasColumn($key))
			$this->columns[$key]['name'] = $name;
		else
			$this->columns[$key] = ['name' => $name];
	}

	public function setAutoIncrement(int|string $key, bool $state = true): void
	{
		if($this->hasColumn($key))
			$this->columns[$key]['autoIncrement'] = $state;
		else
			$this->columns[$key] = ['autoIncrement' => $state];
	}

	public function getColumns(): array
	{
		return $this->columns;
	}

	public function getTableName(): string
	{
		return $this->tableName;
	}

	public function __toString()
	{
		return static::class.' [ table: '.$this->tableName.' ]';
	}

	public function jsonSerialize(): mixed
	{
		return [
			'table' => $this->tableName,
		];
	}

	public function generator() {
		// var_dump('generator');
	}
}
