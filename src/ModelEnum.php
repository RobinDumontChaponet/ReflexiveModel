<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Core\Comparator;

trait ModelEnum
{
	public function getId(): int|string
	{
		return $this->name;
	}

	/*
	 * Active Record
	 */
	public static function search(?string $name = null, ?Comparator $comparator = null, string|int|float|array|bool $value = null): ModelStatement
	{

		$query = new Search(static::class);

		if(isset($name))
			$query->where($name, $comparator, $value);

		return $query;
	}

	public static function create(Model &$model): ModelStatement
	{
		return new Create($model);
	}

	public static function read(?string $name = null, ?Comparator $comparator = null, string|int|float|array|bool $value = null): ModelStatement
	{
		$query = new Read(static::class);

		if(isset($name))
			$query->where($name, $comparator, $value);

		return $query;
	}

	public static function update(Model &$model): ModelStatement
	{
		return new Update($model);
	}

	public static function delete(Model &$model): ModelStatement
	{
		return new Delete($model);
	}

	public static function count(): ModelStatement
	{
		return new Count(static::class);
	}
}
