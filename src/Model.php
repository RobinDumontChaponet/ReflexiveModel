<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Core\Comparator;
use ReflectionClass;

abstract class Model implements \JsonSerializable
{
	protected static array $getters = [];
	protected static array $attributed = [];

	protected array $modifiedProperties = [];
	public bool $ignoreModifiedProperties = false;

	public function getModifiedPropertiesNames(): array
	{
		return $this->modifiedProperties;
	}

	public function resetModifiedPropertiesNames(): void
	{
		$this->modifiedProperties = [];
	}

	public static function initModelAttributes(): void
	{
		if(!isset(self::$attributed[static::class])) {
			$classReflection = new ReflectionClass(static::class);

			// get attributes of properties
			foreach($classReflection->getProperties() as $propertyReflection) {
				foreach($propertyReflection->getAttributes(ModelProperty::class) as $attributeReflection) {
					$modelAttribute = $attributeReflection->newInstance();

					if(!empty($modelAttribute->makeGetter))
						static::$getters[static::class][is_string($modelAttribute->makeGetter)? $modelAttribute->makeGetter : 'get'.ucfirst($propertyReflection->getName())] = $propertyReflection->getName();
				}
			}

			self::$attributed[static::class] = true;
		}
	}

    public function __construct(
		#[Column('id', isId: true, autoIncrement: true)]
		protected int $id = -1,
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

	public function __call(string $name, array $arguments): mixed
	{
		$arguments; // shut up, IDEs

		if(isset(static::$getters[static::class][$name])) {
			return static::$getters[static::class][$name]();
		} else {
			set_error_handler(self::errorHandler());
			trigger_error('Call to undefined method '.static::class.'::'.$name.'()', E_USER_ERROR);
		}
	}

	private static function errorHandler(): null|callable
	{
		return function($level, $message, $file, $line) {
			$level; $line; // shut up, IDEs
			if($file == __FILE__) {
				$debug = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

				echo PHP_EOL, '<strong>'.$message.'</strong> in '.($debug[2]['file']??'?').' on line '.($debug[2]['line']??'?'), PHP_EOL;
				return true; // prevent the PHP error handler from continuing
			}
			return false;
		};
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
