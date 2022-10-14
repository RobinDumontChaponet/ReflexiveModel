<?php

namespace Reflexive\Model;

use Reflexive\Core\Comparator;

interface SCRUDInterface
{
	public function getModelId(): int|string|array;
	public function getModelIdString(): ?string;

	public static function search(?string $name = null, ?Comparator $comparator = null, string|int|float|array|bool $value = null): ModelStatement;

	public static function create(Model &$model): ModelStatement;

	public static function read(?string $name = null, ?Comparator $comparator = null, string|int|float|array|bool $value = null): ModelStatement;

	public static function update(Model &$model): ModelStatement;

	public static function delete(Model &$model): ModelStatement;

	public static function count(): ModelStatement;
}
