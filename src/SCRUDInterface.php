<?php

namespace Reflexive\Model;

use Core\Comparator;

interface SCRUDInterface
{
	public static function search(array $on, string $combinator = Comparator::OR, int $limit = null, int $offset = null): array;

    public static function create(Model &$object);

	public static function read(array $on, string $combinator = Comparator::OR, int $limit = null, int $offset = null): ?Model;

    public static function update(Model &$object);

    public static function delete(Model $object);
}
