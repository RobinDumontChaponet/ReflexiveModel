<?php

namespace Reflexive\Model;

use Reflexive\Query\Operator;

interface SCRUDInterface
{
	public static function search(array $on, Operator $combinator = Operator::OR, int $limit = null, int $offset = null): Collection;

    public static function create(Model &$object);

	public static function read(array $on, Operator $combinator = Operator::OR, int $limit = null, int $offset = null): ?Model;

    public static function update(Model &$object);

    public static function delete(Model $object);
}
