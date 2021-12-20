<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Core\Comparator;
use ReflectionClass;

abstract class Model implements \JsonSerializable
{
	protected static array $getters = [];
	protected static array $attributed = [];

	public static function initModelAttributes(): void
	{
		if(!isset(self::$attributed[static::class])) {
			$classReflection = new ReflectionClass(static::class);

			// get attributes of properties
			foreach($classReflection->getProperties() as $propertyReflection) {
				foreach($propertyReflection->getAttributes(ModelProperty::class) as $attributeReflection) {
					$modelAttribute = $attributeReflection->newInstance();

					if(!empty($modelAttribute->makeGetter))
						static::$getters[is_string($modelAttribute->makeGetter)? $modelAttribute->makeGetter : 'get'.ucfirst($propertyReflection->getName())] = $propertyReflection->getName();
				}
			}

			self::$attributed[static::class] = true;
		}
	}

    public function __construct(
		#[ModelProperty('id', autoIncrement: true)]
		protected int|string $id = -1,
	)
	{
		static::initModelAttributes();
	}

    public function getId(): int|string
    {
        return $this->id;
    }

    public function setId(int|string $id): void
    {
        $this->id = $id;
    }

    public function __toString()
    {
        return  static::class.' [ id: '.$this->id.(((!get_parent_class())) ? ' ]' : ';  ');
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->getId(),
        ];
    }

	// function __get(string $name): mixed
	// {
	// 	if (array_key_exists($name, $this->model_data)) {
	// 		return $this->model_data[$name];
	// 	}
	// 	return null;
	// }

	public function __call($name, $arguments)
	{
		if(isset($this->getters[$name])) {
			return $this->{$this->getters[$name]};
		} else {
			trigger_error('Call to undefined method '.__CLASS__.'::'.$name.'()', E_USER_ERROR);
		}
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
		$query = new Create($model);
		return $query;
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
		$query = new Update($model);
		return $query;
	}
}
