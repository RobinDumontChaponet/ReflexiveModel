<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Core\Comparator;

class Schema implements \JsonSerializable
{
	protected array $columns = [];

	public function __construct(
		protected string $tableName,
	)
	{}

	public function getColumnName(int|string $key): ?string
	{
		return $this->columns[$key] ?? null;
	}

	public function setColumnName(int|string $key, string $name): void
	{
		$this->columns[$key] = $name;
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
